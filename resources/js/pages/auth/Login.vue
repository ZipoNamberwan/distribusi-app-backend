<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();
</script>

<template>
    <div class="page-root">
        <!-- ── Left: Illustration panel ── -->
        <div class="illus-panel">
            <!-- Brand -->
            <div class="brand">
                <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                    <rect width="32" height="32" rx="9" fill="white" fill-opacity="0.18" />
                    <path d="M16 5L27 11V21L16 27L5 21V11L16 5Z" stroke="white" stroke-width="1.8"
                        stroke-linejoin="round" />
                    <path d="M16 5V16M16 16L27 11M16 16L5 11M16 16V27" stroke="white" stroke-width="1.4"
                        opacity="0.55" />
                </svg>
                <span>Workspace</span>
            </div>

            <!-- Illustration -->
            <div class="illus-center">
                <svg class="main-illustration" viewBox="0 0 480 400" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <!-- Background blobs -->
                    <ellipse cx="240" cy="340" rx="200" ry="40" fill="rgba(255,255,255,0.06)" />
                    <circle cx="120" cy="180" r="90" fill="rgba(255,255,255,0.04)" />
                    <circle cx="370" cy="140" r="70" fill="rgba(255,255,255,0.04)" />

                    <!-- Dashboard card shadow -->
                    <rect x="80" y="90" width="320" height="220" rx="20" fill="rgba(0,0,0,0.18)" />

                    <!-- Dashboard card -->
                    <rect x="76" y="86" width="320" height="220" rx="20" fill="white" fill-opacity="0.12" />
                    <rect x="76" y="86" width="320" height="220" rx="20" stroke="white" stroke-opacity="0.2"
                        stroke-width="1.2" />

                    <!-- Card top bar -->
                    <rect x="76" y="86" width="320" height="48" rx="20" fill="white" fill-opacity="0.1" />
                    <rect x="76" y="110" width="320" height="24" fill="white" fill-opacity="0.1" />
                    <circle cx="104" cy="110" r="6" fill="#ff6b6b" fill-opacity="0.8" />
                    <circle cx="122" cy="110" r="6" fill="#ffd93d" fill-opacity="0.8" />
                    <circle cx="140" cy="110" r="6" fill="#6bcb77" fill-opacity="0.8" />

                    <!-- Chart bars -->
                    <rect x="112" y="200" width="28" height="70" rx="6" fill="white" fill-opacity="0.35" />
                    <rect x="152" y="170" width="28" height="100" rx="6" fill="white" fill-opacity="0.55" />
                    <rect x="192" y="185" width="28" height="85" rx="6" fill="white" fill-opacity="0.4" />
                    <rect x="232" y="155" width="28" height="115" rx="6" fill="white" fill-opacity="0.7" />
                    <rect x="272" y="175" width="28" height="95" rx="6" fill="white" fill-opacity="0.5" />
                    <rect x="312" y="145" width="28" height="125" rx="6" fill="white" fill-opacity="0.75" />

                    <!-- Line chart overlay -->
                    <polyline points="126,215 166,185 206,200 246,165 286,182 326,155" stroke="white" stroke-width="2.5"
                        stroke-linecap="round" stroke-linejoin="round" fill="none" stroke-opacity="0.9" />
                    <circle cx="126" cy="215" r="4.5" fill="white" fill-opacity="0.9" />
                    <circle cx="166" cy="185" r="4.5" fill="white" fill-opacity="0.9" />
                    <circle cx="206" cy="200" r="4.5" fill="white" fill-opacity="0.9" />
                    <circle cx="246" cy="165" r="4.5" fill="white" fill-opacity="0.9" />
                    <circle cx="286" cy="182" r="4.5" fill="white" fill-opacity="0.9" />
                    <circle cx="326" cy="155" r="4.5" fill="white" fill-opacity="0.9" />

                    <!-- Small stat cards -->
                    <rect x="90" y="320" width="85" height="46" rx="12" fill="white" fill-opacity="0.13" stroke="white"
                        stroke-opacity="0.18" stroke-width="1" />
                    <rect x="188" y="320" width="85" height="46" rx="12" fill="white" fill-opacity="0.13" stroke="white"
                        stroke-opacity="0.18" stroke-width="1" />
                    <rect x="286" y="320" width="85" height="46" rx="12" fill="white" fill-opacity="0.13" stroke="white"
                        stroke-opacity="0.18" stroke-width="1" />
                    <rect x="103" y="330" width="40" height="6" rx="3" fill="white" fill-opacity="0.4" />
                    <rect x="103" y="342" width="56" height="8" rx="3" fill="white" fill-opacity="0.7" />
                    <rect x="201" y="330" width="40" height="6" rx="3" fill="white" fill-opacity="0.4" />
                    <rect x="201" y="342" width="56" height="8" rx="3" fill="white" fill-opacity="0.7" />
                    <rect x="299" y="330" width="40" height="6" rx="3" fill="white" fill-opacity="0.4" />
                    <rect x="299" y="342" width="56" height="8" rx="3" fill="white" fill-opacity="0.7" />

                    <!-- Floating notification badge -->
                    <rect x="310" y="58" width="130" height="44" rx="12" fill="white" fill-opacity="0.14" stroke="white"
                        stroke-opacity="0.22" stroke-width="1" />
                    <circle cx="328" cy="80" r="8" fill="white" fill-opacity="0.3" />
                    <rect x="342" y="70" width="68" height="7" rx="3.5" fill="white" fill-opacity="0.55" />
                    <rect x="342" y="82" width="48" height="6" rx="3" fill="white" fill-opacity="0.35" />

                    <!-- Avatar cluster -->
                    <circle cx="100" cy="62" r="18" fill="white" fill-opacity="0.15" stroke="white"
                        stroke-opacity="0.25" stroke-width="1" />
                    <circle cx="126" cy="62" r="18" fill="white" fill-opacity="0.15" stroke="white"
                        stroke-opacity="0.25" stroke-width="1" />
                    <circle cx="152" cy="62" r="18" fill="white" fill-opacity="0.15" stroke="white"
                        stroke-opacity="0.25" stroke-width="1" />
                    <circle cx="100" cy="58" r="6" fill="white" fill-opacity="0.5" />
                    <path d="M90 70 Q100 66 110 70" stroke="white" stroke-opacity="0.5" stroke-width="1.5" fill="none"
                        stroke-linecap="round" />
                    <circle cx="126" cy="58" r="6" fill="white" fill-opacity="0.5" />
                    <path d="M116 70 Q126 66 136 70" stroke="white" stroke-opacity="0.5" stroke-width="1.5" fill="none"
                        stroke-linecap="round" />
                    <circle cx="152" cy="58" r="6" fill="white" fill-opacity="0.5" />
                    <path d="M142 70 Q152 66 162 70" stroke="white" stroke-opacity="0.5" stroke-width="1.5" fill="none"
                        stroke-linecap="round" />
                </svg>

                <div class="illus-caption">
                    <h1>Everything you need,<br />in one place.</h1>
                    <p>Track performance, manage your team,<br />and make smarter decisions.</p>
                </div>
            </div>

            <div class="blob blob-1"></div>
            <div class="blob blob-2"></div>
        </div>

        <!-- ── Right: Form panel ── -->
        <div class="form-panel">
            <div class="form-inner">
                <div v-if="status" class="status-banner">
                    {{ status }}
                </div>

                <div class="form-header">
                    <h2>Welcome back</h2>
                    <p>Sign in to your account to continue</p>
                </div>

                <!-- SSO -->
                <button type="button" class="sso-button">
                    <svg width="18" height="18" viewBox="0 0 20 20" fill="none">
                        <path
                            d="M19.6 10.23c0-.68-.06-1.36-.17-2H10v3.79h5.39a4.6 4.6 0 01-2 3.02v2.51h3.24C18.34 15.86 19.6 13.24 19.6 10.23z"
                            fill="#4285F4" />
                        <path
                            d="M10 20c2.7 0 4.96-.9 6.62-2.44l-3.24-2.51c-.9.6-2.04.96-3.38.96-2.6 0-4.8-1.75-5.59-4.11H1.07v2.6A10 10 0 0010 20z"
                            fill="#34A853" />
                        <path
                            d="M4.41 11.9A5.98 5.98 0 014.1 10c0-.66.11-1.3.31-1.9V5.5H1.07A10 10 0 000 10c0 1.61.39 3.13 1.07 4.5l3.34-2.6z"
                            fill="#FBBC05" />
                        <path
                            d="M10 3.98c1.47 0 2.79.5 3.83 1.5L16.69 2.4A10 10 0 0010 0 10 10 0 001.07 5.5l3.34 2.6C5.2 5.74 7.4 3.98 10 3.98z"
                            fill="#EA4335" />
                    </svg>
                    Continue with SSO
                </button>

                <div class="divider"><span>or continue with email</span></div>

                <Form v-bind="store()" :reset-on-success="['password']" v-slot="{ errors, processing }"
                    class="login-form">
                    <div class="field">
                        <Label for="email">Email address</Label>
                        <Input id="email" type="email" name="email" required autofocus :tabindex="1"
                            autocomplete="email" placeholder="you@example.com" />
                        <InputError :message="errors.email" />
                    </div>

                    <div class="field">
                        <div class="field-row">
                            <Label for="password">Password</Label>
                            <TextLink v-if="canResetPassword" :href="request()" class="forgot-link" :tabindex="5">
                                Forgot password?
                            </TextLink>
                        </div>
                        <Input id="password" type="password" name="password" required :tabindex="2"
                            autocomplete="current-password" placeholder="••••••••" />
                        <InputError :message="errors.password" />
                    </div>

                    <Label for="remember" class="remember-label">
                        <Checkbox id="remember" name="remember" :tabindex="3" />
                        <span>Remember me</span>
                    </Label>

                    <Button type="submit" class="submit-button" :tabindex="4" :disabled="processing"
                        data-test="login-button">
                        <Spinner v-if="processing" />
                        <span>{{ processing ? 'Signing in…' : 'Sign in' }}</span>
                    </Button>
                </Form>

                <p class="register-prompt" v-if="canRegister">
                    Don't have an account?
                    <TextLink :href="register()" :tabindex="6">Create one</TextLink>
                </p>
            </div>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&family=Fraunces:wght@400;500&display=swap');

/* ── Root ── */
.page-root {
    display: grid;
    grid-template-columns: 1.1fr 0.9fr;
    min-height: 100vh;
    font-family: 'DM Sans', sans-serif;
    background: #f0f4ff;
}

/* ── Illustration panel ── */
.illus-panel {
    background: linear-gradient(155deg, #2563eb 0%, #1d4ed8 40%, #1e3a8a 100%);
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    padding: 2.5rem 3rem;
}

.brand {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    color: white;
    font-weight: 600;
    font-size: 1.1rem;
    letter-spacing: -0.02em;
    position: relative;
    z-index: 3;
}

.illus-center {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 2.5rem;
    position: relative;
    z-index: 3;
}

.main-illustration {
    width: 100%;
    max-width: 460px;
    filter: drop-shadow(0 24px 48px rgba(0, 0, 0, 0.18));
    animation: float 6s ease-in-out infinite;
}

@keyframes float {

    0%,
    100% {
        transform: translateY(0);
    }

    50% {
        transform: translateY(-10px);
    }
}

.illus-caption {
    text-align: center;
    color: white;
}

.illus-caption h1 {
    font-family: 'Fraunces', serif;
    font-size: 2rem;
    font-weight: 400;
    line-height: 1.25;
    margin: 0 0 0.6rem;
    color: white;
}

.illus-caption p {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.6);
    font-weight: 300;
    line-height: 1.6;
    margin: 0;
}

.blob {
    position: absolute;
    border-radius: 50%;
    filter: blur(60px);
    z-index: 1;
}

.blob-1 {
    width: 380px;
    height: 380px;
    background: rgba(96, 165, 250, 0.25);
    bottom: -100px;
    right: -80px;
}

.blob-2 {
    width: 240px;
    height: 240px;
    background: rgba(147, 197, 253, 0.15);
    top: 5%;
    left: -60px;
}

/* ── Form panel ── */
.form-panel {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem 2.5rem;
    background: #f0f4ff;
}

.form-inner {
    width: 100%;
    max-width: 380px;
    display: flex;
    flex-direction: column;
    gap: 1.2rem;
}

/* ── Status ── */
.status-banner {
    background: #dcfce7;
    color: #166534;
    font-size: 0.85rem;
    padding: 0.7rem 1rem;
    border-radius: 10px;
    text-align: center;
    font-weight: 500;
}

/* ── Header ── */
.form-header h2 {
    font-family: 'Fraunces', serif;
    font-size: 2rem;
    font-weight: 400;
    color: #0f172a;
    letter-spacing: -0.02em;
    margin: 0 0 0.2rem;
}

.form-header p {
    font-size: 0.875rem;
    color: #64748b;
    margin: 0;
}

/* ── SSO ── */
.sso-button {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    width: 100%;
    padding: 0.72rem 1rem;
    background: white;
    border: 1.5px solid #dde3f0;
    border-radius: 11px;
    font-size: 0.875rem;
    font-weight: 500;
    color: #1e293b;
    cursor: pointer;
    font-family: 'DM Sans', sans-serif;
    transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}

.sso-button:hover {
    border-color: #3b82f6;
    background: #fafbff;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* ── Divider ── */
.divider {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: #94a3b8;
    font-size: 0.775rem;
    letter-spacing: 0.03em;
    text-transform: uppercase;
}

.divider::before,
.divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: #dde3f0;
}

/* ── Form ── */
.login-form {
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.field :deep(label) {
    font-size: 0.825rem;
    font-weight: 500;
    color: #374151;
}

.field-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.forgot-link {
    font-size: 0.8rem;
    color: #3b82f6 !important;
    font-weight: 500;
    text-decoration: none;
}

.forgot-link:hover {
    color: #1d4ed8 !important;
}

.field :deep(input) {
    height: 42px;
    border-radius: 10px;
    border: 1.5px solid #dde3f0;
    background: white;
    font-size: 0.875rem;
    padding: 0 0.875rem;
    transition: border-color 0.15s, box-shadow 0.15s;
    font-family: 'DM Sans', sans-serif;
    color: #0f172a;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.field :deep(input:focus) {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
}

.field :deep(input::placeholder) {
    color: #c4cdd6;
}

/* ── Remember ── */
.remember-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    font-size: 0.825rem !important;
    font-weight: 400 !important;
    color: #4b5563 !important;
    margin-top: -0.1rem;
}

/* ── Submit ── */
.submit-button {
    width: 100%;
    height: 44px;
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%) !important;
    border: none !important;
    border-radius: 11px !important;
    font-size: 0.9rem !important;
    font-weight: 600 !important;
    color: white !important;
    cursor: pointer;
    transition: opacity 0.15s, transform 0.1s, box-shadow 0.15s !important;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    box-shadow: 0 4px 16px rgba(59, 130, 246, 0.4) !important;
    margin-top: 0.2rem;
    font-family: 'DM Sans', sans-serif;
}

.submit-button:hover:not(:disabled) {
    opacity: 0.9;
    transform: translateY(-1px);
    box-shadow: 0 6px 22px rgba(59, 130, 246, 0.5) !important;
}

.submit-button:active:not(:disabled) {
    transform: translateY(0);
}

.submit-button:disabled {
    opacity: 0.6;
}

/* ── Register ── */
.register-prompt {
    text-align: center;
    font-size: 0.85rem;
    color: #64748b;
    margin: 0;
}

.register-prompt :deep(a) {
    color: #3b82f6;
    font-weight: 500;
    text-decoration: none;
}

.register-prompt :deep(a:hover) {
    color: #1d4ed8;
}

/* ── Responsive ── */
@media (max-width: 860px) {
    .page-root {
        grid-template-columns: 1fr;
    }

    .illus-panel {
        padding: 2rem;
        min-height: 280px;
    }

    .illus-caption h1 {
        font-size: 1.5rem;
    }

    .main-illustration {
        max-width: 320px;
    }

    .form-panel {
        padding: 2rem 1.5rem 3rem;
        align-items: flex-start;
    }
}
</style>