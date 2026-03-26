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
import { register } from "@/routes";
import { store } from "@/routes/login";
import { request } from "@/routes/password";
import { index as ssoIndex } from "@/routes/sso/login";
import { index as majapahitIndex } from "@/routes/majapahit/login";

defineProps<{
  status?: string;
  canResetPassword: boolean;
  canRegister: boolean;
}>();

const handleSsoLogin = () => {
  window.location.href = ssoIndex().url;
};

const handleMajapahitLogin = () => {
  window.location.href = majapahitIndex().url;
};
</script>

<template>
  <AuthBase
    title="Masuk ke akun Anda"
    description="Pilih salah satu metode masuk untuk melanjutkan"
  >
    <Head title="Log in" />

    <div
      v-if="status"
      class="mb-4 text-center text-sm font-medium text-green-600 dark:text-green-400"
    >
      {{ status }}
    </div>

    <!-- SSO Login Section -->
    <div class="flex flex-col gap-6">
      <Button
        type="button"
        class="w-full h-12 text-base font-semibold bg-gradient-to-r from-blue-900 via-blue-700 to-blue-600 hover:from-blue-800 hover:via-blue-600 hover:to-blue-500 dark:from-blue-700 dark:via-blue-600 dark:to-blue-500 dark:hover:from-blue-600 dark:hover:via-blue-500 dark:hover:to-blue-400 shadow-lg shadow-blue-500/30 hover:shadow-blue-500/40 dark:shadow-blue-400/20 dark:hover:shadow-blue-400/30 transition-all !text-white"
        @click="handleSsoLogin"
      >
        <img src="/images/bps.svg" alt="BPS" class="w-5 h-5 mr-3 object-contain" />
        Lanjutkan dengan SSO
      </Button>

      <Button
        type="button"
        class="w-full h-12 text-base font-semibold bg-gradient-to-r from-purple-900 via-purple-700 to-purple-600 hover:from-purple-800 hover:via-purple-600 hover:to-purple-500 dark:from-purple-700 dark:via-purple-600 dark:to-purple-500 dark:hover:from-purple-600 dark:hover:via-purple-500 dark:hover:to-purple-400 shadow-lg shadow-purple-500/30 hover:shadow-purple-500/40 dark:shadow-purple-400/20 dark:hover:shadow-purple-400/30 transition-all !text-white"
        @click="handleMajapahitLogin"
      >
        <img src="/images/majapahit.png" alt="Majapahit" class="w-5 h-5 mr-2 object-contain" />
        Lanjutkan dengan Majapahit
      </Button>

      <!-- Info Section -->
      <!-- <div class="space-y-3 mt-2">
        <div class="flex items-start space-x-3 text-sm text-muted-foreground">
          <svg
            class="w-5 h-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5"
            fill="currentColor"
            viewBox="0 0 20 20"
          >
            <path
              fill-rule="evenodd"
              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
              clip-rule="evenodd"
            />
          </svg>
          <span>Single Sign-On BPS untuk akses yang lebih aman</span>
        </div>
        <div class="flex items-start space-x-3 text-sm text-muted-foreground">
          <svg
            class="w-5 h-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5"
            fill="currentColor"
            viewBox="0 0 20 20"
          >
            <path
              fill-rule="evenodd"
              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
              clip-rule="evenodd"
            />
          </svg>
          <span>Integrasi login dengan aplikasi BPS lainnya</span>
        </div>
      </div> -->
    </div>

    <!-- Original Login Form (Commented Out) -->
    <!--
        <Form
            v-bind="store.form()"
            :reset-on-success="['password']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="email"
                        placeholder="email@example.com"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="password">Password</Label>
                        <TextLink
                            v-if="canResetPassword"
                            :href="request()"
                            class="text-sm"
                            :tabindex="5"
                        >
                            Forgot password?
                        </TextLink>
                    </div>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        placeholder="Password"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="flex items-center justify-between">
                    <Label for="remember" class="flex items-center space-x-3">
                        <Checkbox id="remember" name="remember" :tabindex="3" />
                        <span>Remember me</span>
                    </Label>
                </div>

                <Button
                    type="submit"
                    class="mt-6 w-full shadow-lg shadow-primary/20 text-base h-11 font-semibold transition-all hover:shadow-primary/30"
                    :tabindex="4"
                    :disabled="processing"
                    data-test="login-button"
                >
                    <Spinner v-if="processing" class="mr-2" />
                    Log in
                </Button>
            </div>

            <div
                class="text-center text-sm text-muted-foreground"
                v-if="canRegister"
            >
                Don't have an account?
                <TextLink :href="register()" :tabindex="5">Sign up</TextLink>
            </div>
        </Form>
        -->
  </AuthBase>
</template>
