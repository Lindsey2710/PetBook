<script>
import { Head, Link } from "@inertiajs/vue3";
import PrimaryButton from "@/Components/PrimaryButton.vue";

export default {
    name: "Welcome",
    components: {
        Head,
        Link,
        PrimaryButton,
    },
    props: {
        isAuthenticated: Boolean,
    },
    setup() {
        return {};
    },
};
</script>

<template>
    <Head title="Welcome" />
    <div className="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-black text-white/50 main-container">
        <div className="flex flex-col items-center justify-center">
            <!-- Logo -->
            <div className="flex items-center gap-2 mt-5">
                <img src="/assets/images/logo.svg" alt="logo" className="w-16 h-16" />
                <h1 className="text-5xl font-bold">
                    <span
                        className="bg-gradient-to-r from-indigo-500 from-10% via-sky-500 via-30% to-emerald-500 to-90% inline-block text-transparent bg-clip-text">
                        PetBook
                    </span>
                </h1>
            </div>
        </div>
        <!-- End of Logo -->

        <div className="flex flex-col items-center justify-center mt-10">
            <div className="flex flex-col items-center justify-center">
                <p className="text-2xl font-bold text-white">
                    Welcome to PetBook
                </p>
                <div className="flex flex-col items-center justify-center mt-5">
                    <p className="text-md text-white text-center">
                        Join PetBook – the ultimate platform for pet lovers!
                        <br>
                        Connect through adorable pet posts. Follow friends by searching their name, enjoy cute pictures and videos.
                        <br>
                        Let the world see your furry friends. Start sharing today!
                    </p>
                    <img src="/assets/images/instapets.jpg" alt="pets"
                        className="w-60 max-w-lg mt-5 rounded-full mb-5" />
                </div>
            </div>
        </div>
        <div className="flex flex-col items-center justify-center">

                <nav className="-mx-3 flex flex-1 justify-center mt-2">
                    <PrimaryButton v-if="!isAuthenticated" :href="route('login')"
                        className="rounded-md px-3 py-2 ring-2 ring-indigo-900 transition hover:outline-indigo-900 dark:text-white/80 dark:hover:text-white">
                        Log in
                    </PrimaryButton>

                    <PrimaryButton v-if="!isAuthenticated" :href="route('register')"
                        className="rounded-md px-3 py-2 ml-6 ring-2 ring-indigo-900 transition hover:outline-indigo-900 dark:text-white/80 dark:hover:text-white">
                        Register
                    </PrimaryButton>

                    <PrimaryButton v-if="isAuthenticated" :href="route('dashboard')"
                    className="rounded-md px-3 py-2 ring-2 ring-indigo-900 transition hover:outline-indigo-900 dark:text-white/80 dark:hover:text-white">
                      Go To Homefeed
                    </PrimaryButton>
                </nav>


            <footer className="py-16 text-center text-sm  dark:text-white footer">
                <a href="https://www.linkatver.com" target="_blank" rel="noopener noreferrer">
                    Created by a true animal lover
                </a>
                <br />
                <a href="https://www.linkatver.com" target="_blank" rel="noopener noreferrer"
                    className="text-sm text-white hover:text-white/80 hover:underline">
                    &copy; 2025 Linkatver
                </a>
            </footer>
        </div>
    </div>
</template>
