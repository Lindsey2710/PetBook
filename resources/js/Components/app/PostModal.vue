<script setup>
import { computed, ref, watch, onMounted, onUnmounted } from "vue";
import "emoji-picker-element";
import {
  XMarkIcon,
  PaperClipIcon,
  BookmarkIcon,
  ArrowUturnLeftIcon,
  FaceSmileIcon,
} from "@heroicons/vue/24/solid";
import PostUserHeader from "@/Components/app/PostUserHeader.vue";
import { useForm, usePage } from "@inertiajs/vue3";
import ClassicEditor from "@ckeditor/ckeditor5-build-classic";
import { isImage } from "@/helpers.js";
import axiosClient from "@/axiosClient.js";
import UrlPreview from "@/Components/app/UrlPreview.vue";
import BaseModal from "@/Components/app/BaseModal.vue";

const editor = ClassicEditor;
const editorConfig = {
  mediaEmbed: {
    removeProviders: [
      "dailymotion",
      "spotify",
      "youtube",
      "vimeo",
      "instagram",
      "twitter",
      "googleMaps",
      "flickr",
      "facebook",
    ],
  },
  toolbar: [
    "bold",
    "italic",
    "|",
    "bulletedList",
    "numberedList",
    "|",
    "heading",
    "|",
    "outdent",
    "indent",
    "|",
    "link",
    "|",
    "blockQuote",
  ],
};

const props = defineProps({
  post: {
    type: Object,
    required: true,
  },
  group: {
    type: Object,
    default: null,
  },
  modelValue: Boolean,
});

const attachmentExtensions = usePage().props.attachmentExtensions;
/**
 * {
 *     file: File,
 *     url: '',
 * }
 * @type {Ref<UnwrapRef<*[]>>}
 */
const attachmentFiles = ref([]);
const attachmentErrors = ref([]);
const formErrors = ref({});
const aiButtonLoading = ref(false);

const form = useForm({
  body: "",
  group_id: null,
  attachments: [],
  deleted_file_ids: [],
  preview: {},
  preview_url: null,
  _method: "POST",
});

const show = computed({
  get: () => props.modelValue,
  set: (value) => emit("update:modelValue", value),
});

const computedAttachments = computed(() => {
  return [...attachmentFiles.value, ...(props.post.attachments || [])];
});
const showExtensionsText = computed(() => {
  for (let myFile of attachmentFiles.value) {
    const file = myFile.file;
    let parts = file.name.split(".");
    let ext = parts.pop().toLowerCase();
    if (!attachmentExtensions.includes(ext)) {
      return true;
    }
  }

  return false;
});

const emit = defineEmits(["update:modelValue", "hide"]);

watch(
  () => props.post,
  () => {
    form.body = props.post.body || "";
    onInputChange();
  }
);

function closeModal() {
  show.value = false;
  emit("hide");
  resetModal();
}

function resetModal() {
  form.reset();
  formErrors.value = {};
  attachmentFiles.value = [];
  attachmentErrors.value = [];
  if (props.post.attachments) {
    props.post.attachments.forEach((file) => (file.deleted = false));
  }
}

function submit() {
  if (props.group) {
    form.group_id = props.group.id;
  }
  form.attachments = attachmentFiles.value.map((myFile) => myFile.file);
  if (props.post.id) {
    form._method = "PUT";
    form.post(route("post.update", props.post.id), {
      preserveScroll: true,
      onSuccess: (res) => {
        closeModal();
      },
      onError: (errors) => {
        processErrors(errors);
      },
    });
  } else {
    form.post(route("post.create"), {
      preserveScroll: true,
      onSuccess: (res) => {
        closeModal();
      },
      onError: (errors) => {
        processErrors(errors);
      },
    });
  }
}

function processErrors(errors) {
  formErrors.value = errors;
  for (const key in errors) {
    if (key.includes(".")) {
      const [, index] = key.split(".");
      attachmentErrors.value[index] = errors[key];
    }
  }
}

async function onAttachmentChoose($event) {
  for (const file of $event.target.files) {
    const myFile = {
      file,
      url: await readFile(file),
    };
    attachmentFiles.value.push(myFile);
  }
  $event.target.value = null;
}

async function readFile(file) {
  return new Promise((res, rej) => {
    if (isImage(file)) {
      const reader = new FileReader();
      reader.onload = () => {
        res(reader.result);
      };
      reader.onerror = rej;
      reader.readAsDataURL(file);
    } else {
      res(null);
    }
  });
}

function removeFile(myFile) {
  if (myFile.file) {
    attachmentFiles.value = attachmentFiles.value.filter((f) => f !== myFile);
  } else {
    form.deleted_file_ids.push(myFile.id);
    myFile.deleted = true;
  }
}

function undoDelete(myFile) {
  myFile.deleted = false;
  form.deleted_file_ids = form.deleted_file_ids.filter((id) => myFile.id !== id);
}

function getAIContent() {
  if (!form.body) {
    return;
  }
  aiButtonLoading.value = true;
  axiosClient
    .post(route("post.aiContent"), {
      prompt: form.body,
    })
    .then(({ data }) => {
      if (data.content) {
        form.body = data.content;
      } else {
        console.error("No content received from API");
        alert("Failed to generate content. Please try again.");
      }
    })
    .catch((err) => {
      console.error("AI Content Error:", err);
      const errorMessage =
        err.response?.data?.error || "Failed to generate content. Please try again.";

      // Show a more friendly message for the loading state
      if (errorMessage.includes("warming up")) {
        alert("The AI model is warming up. " + errorMessage);
      } else {
        alert(errorMessage);
      }
    })
    .finally(() => {
      aiButtonLoading.value = false;
    });
}

function fetchPreview(url) {
  if (url === form.preview_url) {
    return;
  }

  form.preview_url = url;
  form.preview = {};
  if (url) {
    axiosClient
      .post(route("post.fetchUrlPreview"), { url })
      .then(({ data }) => {
        form.preview = {
          title: data["og:title"],
          description: data["og:description"],
          image: data["og:image"],
        };
      })
      .catch((err) => {
        console.log(err);
      });
  }
}

function onInputChange() {
  let url = matchHref();

  if (!url) {
    url = matchLink();
  }
  fetchPreview(url);
}

function matchHref() {
  // Regular expression to match URLs
  const urlRegex = /<a.+href="((https?):\/\/[^"]+)"/;

  // Match the first URL in the HTML content
  const match = form.body.match(urlRegex);

  // Check if a match is found
  if (match && match.length > 0) {
    return match[1];
  }
  return null;
}

function matchLink() {
  // Regular expression to match URLs
  const urlRegex = /(?:https?):\/\/[^\s<]+/;

  // Match the first URL in the HTML content
  const match = form.body.match(urlRegex);

  // Check if a match is found
  if (match && match.length > 0) {
    return match[0];
  }
  return null;
}

const showEmojiPicker = ref(false);
const emojiButtonRef = ref(null);

// Simple emoji list
const emojis = [
  "😀",
  "😃",
  "😄",
  "😁",
  "😅",
  "😂",
  "🤣",
  "😊",
  "😇",
  "🙂",
  "🙃",
  "😉",
  "😌",
  "😍",
  "🥰",
  "😘",
  "😗",
  "😙",
  "😚",
  "😋",
  "😛",
  "😝",
  "😜",
  "🤪",
  "🤨",
  "🧐",
  "🤓",
  "😎",
  "🤩",
  "🥳",
  "😏",
  "😒",
  "😞",
  "😔",
  "😟",
  "😕",
  "🙁",
  "☹️",
  "😣",
  "❤️",
  "🧡",
  "💛",
  "💚",
  "💙",
  "💜",
  "🤎",
  "🖤",
  "🤍",
  "💯",
  "✨",
  "💫",
  "🌟",
  "🐶",
  "🐱",
  "🐭",
  "🐹",
  "🐰",
  "🦊",
  "🐻",
  "🐼",
  "🐨",
  "🐯",
  "🦁",
  "🐮",
  "🐷",
];

function onEmojiSelect(emoji) {
  form.body += emoji;
  showEmojiPicker.value = false;
}

onMounted(() => {
  const picker = document.querySelector("emoji-picker");
  if (picker) {
    picker.addEventListener("emoji-click", onEmojiSelect);
  }
});

onUnmounted(() => {
  const picker = document.querySelector("emoji-picker");
  if (picker) {
    picker.removeEventListener("emoji-click", onEmojiSelect);
  }
});
</script>

<template>
  <BaseModal
    :title="post.id ? 'Update Post' : 'Create Post'"
    v-model="show"
    @hide="closeModal"
  >
    <div class="p-4">
      <PostUserHeader :post="post" :show-time="false" class="mb-4 dark:text-gray-100" />

      <div
        v-if="formErrors.group_id"
        class="bg-red-400 py-2 px-3 rounded text-white mb-3"
      >
        {{ formErrors.group_id }}
      </div>

      <div class="relative group">
        <ckeditor
          :editor="editor"
          v-model="form.body"
          :config="editorConfig"
          @input="onInputChange"
        ></ckeditor>

        <UrlPreview :preview="form.preview" :url="form.preview_url" />

        <button
          ref="emojiButtonRef"
          @click="showEmojiPicker = !showEmojiPicker"
          class="absolute right-1 top-12 w-8 h-8 p-1 rounded bg-indigo-500 hover:bg-indigo-600 text-white flex justify-center items-center transition-all opacity-0 group-hover:opacity-100"
        >
          <FaceSmileIcon class="w-4 h-4" />
        </button>

        <div
          v-if="showEmojiPicker"
          class="absolute right-0 z-50 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 p-2 w-64"
          v-click-outside="() => (showEmojiPicker = false)"
        >
          <div class="grid grid-cols-8 gap-1">
            <button
              v-for="emoji in emojis"
              :key="emoji"
              @click="onEmojiSelect(emoji)"
              class="w-8 h-8 flex items-center justify-center hover:bg-gray-100 dark:hover:bg-gray-700 rounded"
            >
              {{ emoji }}
            </button>
          </div>
        </div>
      </div>

      <div
        v-if="showExtensionsText"
        class="border-l-4 border-amber-500 py-2 px-3 bg-amber-100 mt-3 text-gray-800"
      >
        Files must be one of the following extensions <br />
        <small>{{ attachmentExtensions.join(", ") }}</small>
      </div>

      <div
        v-if="formErrors.attachments"
        class="border-l-4 border-red-500 py-2 px-3 bg-red-100 mt-3 text-gray-800"
      >
        {{ formErrors.attachments }}
      </div>

      <div
        class="grid gap-3 my-3"
        :class="[computedAttachments.length === 1 ? 'grid-cols-1' : 'grid-cols-2']"
      >
        <div v-for="(myFile, ind) of computedAttachments">
          <div
            class="group aspect-square bg-blue-100 flex flex-col items-center justify-center text-gray-500 relative border-2"
            :class="attachmentErrors[ind] ? 'border-red-500' : ''"
          >
            <div
              v-if="myFile.deleted"
              class="absolute z-10 left-0 bottom-0 right-0 py-2 px-3 text-sm bg-black text-white flex justify-between items-center"
            >
              To be deleted

              <ArrowUturnLeftIcon
                @click="undoDelete(myFile)"
                class="w-4 h-4 cursor-pointer"
              />
            </div>

            <button
              @click="removeFile(myFile)"
              class="absolute z-20 right-3 top-3 w-7 h-7 flex items-center justify-center bg-black/30 text-white rounded-full hover:bg-black/40"
            >
              <XMarkIcon class="h-5 w-5" />
            </button>

            <img
              v-if="isImage(myFile.file || myFile)"
              :src="myFile.url"
              class="object-contain aspect-square"
              :class="myFile.deleted ? 'opacity-50' : ''"
            />
            <div
              v-else
              class="flex flex-col justify-center items-center px-3"
              :class="myFile.deleted ? 'opacity-50' : ''"
            >
              <PaperClipIcon class="w-10 h-10 mb-3" />

              <small class="text-center">
                {{ (myFile.file || myFile).name }}
              </small>
            </div>
          </div>
          <small class="text-red-500">{{ attachmentErrors[ind] }}</small>
        </div>
      </div>
    </div>

    <div class="flex gap-2 py-3 px-4">
      <button
        type="button"
        class="flex items-center justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 w-full relative"
      >
        <PaperClipIcon class="w-4 h-4 mr-2" />
        Attach Files
        <input
          @click.stop
          @change="onAttachmentChoose"
          type="file"
          multiple
          class="absolute left-0 top-0 right-0 bottom-0 opacity-0"
        />
      </button>
      <button
        type="button"
        class="flex items-center justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 w-full"
        @click="submit"
      >
        <BookmarkIcon class="w-4 h-4 mr-2" />
        Submit
      </button>
    </div>
  </BaseModal>
</template>

<style>
.emoji-grid {
  display: grid;
  grid-template-columns: repeat(8, 1fr);
  gap: 4px;
}
</style>
