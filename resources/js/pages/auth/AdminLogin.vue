<script setup lang="ts">
import { Form, Head } from "@inertiajs/vue3";
import InputError from "@/components/InputError.vue";
import TextLink from "@/components/TextLink.vue";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";
import AuthBase from "@/layouts/AuthLayout.vue";
// import { register } from "@/routes";
import { store } from "@/routes/login";
// import { request } from "@/routes/password";
import { index as loginIndex } from "@/routes/sso/login";

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();
</script>

<template>
    <AuthBase title="Masuk ke akun Anda" description="Masuk dengan aman menggunakan Single Sign-On">

        <Head title="Log in" />

        <div v-if="status" class="mb-4 text-center text-sm font-medium text-green-600 dark:text-green-400">
            {{ status }}
        </div>

        <!-- SSO Login Section -->
        <div class="flex flex-col gap-6">

            <!-- Original Login Form (Commented Out) -->
            <Form v-bind="store.form()" :reset-on-success="['password']" v-slot="{ errors, processing }"
                class="flex flex-col gap-6">
                <div class="grid gap-6">
                    <div class="grid gap-2">
                        <Label for="email">Alamat Email</Label>
                        <Input id="email" type="email" name="email" required autofocus :tabindex="1"
                            autocomplete="email" placeholder="email@contoh.com" />
                        <InputError :message="errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <!-- <div class="flex items-center justify-between">
                            <Label for="password">Kata Sandi</Label>
                            <TextLink v-if="canResetPassword" :href="request()" class="text-sm" :tabindex="5">
                                Lupa kata sandi?
                            </TextLink>
                        </div> -->
                        <Input id="password" type="password" name="password" required :tabindex="2"
                            autocomplete="current-password" placeholder="Kata Sandi" />
                        <InputError :message="errors.password" />
                    </div>

                    <div class="flex items-center justify-between">
                        <Label for="remember" class="flex items-center space-x-3">
                            <Checkbox id="remember" name="remember" :tabindex="3" />
                            <span>Ingat saya</span>
                        </Label>
                    </div>

                    <Button type="submit"
                        class="mt-6 w-full shadow-lg shadow-primary/20 text-base h-11 font-semibold transition-all hover:shadow-primary/30"
                        :tabindex="4" :disabled="processing" data-test="login-button">
                        <Spinner v-if="processing" class="mr-2" />
                        Masuk
                    </Button>
                </div>

                <!-- <div class="text-center text-sm text-muted-foreground" v-if="canRegister">
                    Belum punya akun?
                    <TextLink :href="register()" :tabindex="5">Daftar</TextLink>
                </div> -->
            </Form>
        </div>
    </AuthBase>
</template>
