<script setup>
import { computed, reactive, ref, onMounted } from 'vue';
import mathImage from '../../public/images/math.svg';
import englishImage from '../../public/images/english.svg';
import scienceImage from '../../public/images/science.svg';

const TOKEN_KEY = 'mindful_api_token';

const subjectMeta = {
    math: { id: 'math', title: 'Math Galaxy', icon: '🔢', image: mathImage, accent: 'from-purple-500 to-pink-500' },
    english: { id: 'english', title: 'English Jungle', icon: '📚', image: englishImage, accent: 'from-cyan-500 to-blue-500' },
    science: { id: 'science', title: 'Science Lab', icon: '🔬', image: scienceImage, accent: 'from-emerald-500 to-teal-500' },
};

const ageGroups = [
    { id: '4-6', label: '4-6 years (Foundations)' },
    { id: '7-9', label: '7-9 years (Explorers)' },
    { id: '10-12', label: '10-12 years (Advanced)' },
];

const authMode = ref('register');
const authForm = reactive({
    name: '',
    email: '',
    password: '',
    role: 'learner',
    ageGroup: '7-9',
});

const learnerForm = reactive({
    name: '',
    email: '',
    password: '',
    ageGroup: '7-9',
    gradeLevel: '',
});

const token = ref(localStorage.getItem(TOKEN_KEY) || '');
const user = ref(null);
const selectedSubject = ref('math');
const selectedTab = ref('quiz');
const currentCard = ref(null);
const selectedOption = ref('');
const freeTextAnswer = ref('');
const stars = ref(120);
const streak = ref(2);
const attempts = ref([]);
const linkedLearners = ref([]);
const activeLearner = ref(null);
const learnerProgress = ref(null);
const weeklyReport = ref(null);
const loading = ref(false);
const feedback = ref({ text: 'Register or login to continue.', tone: 'idle' });

const subjects = computed(() => Object.values(subjectMeta));
const currentSubject = computed(() => subjectMeta[selectedSubject.value]);
const effectiveAgeGroup = computed(() => {
    if (user.value?.role === 'parent') {
        return activeLearner.value?.age_group || authForm.ageGroup;
    }
    return user.value?.age_group || authForm.ageGroup;
});
const learnerCompletion = computed(() => Math.min(98, 25 + attempts.value.length * 4));
const weakArea = computed(() => {
    const missed = attempts.value.find((attempt) => !attempt.isCorrect);
    return missed ? subjectMeta[missed.subject].title : 'No weak area detected yet';
});

const authHeaders = () => ({
    'Content-Type': 'application/json',
    Accept: 'application/json',
    Authorization: `Bearer ${token.value}`,
});

const setToken = (newToken) => {
    token.value = newToken;
    localStorage.setItem(TOKEN_KEY, newToken);
};

const clearToken = () => {
    token.value = '';
    localStorage.removeItem(TOKEN_KEY);
};

const loadSession = async () => {
    if (!token.value) return;

    try {
        const response = await fetch('/api/v1/auth/me', { headers: authHeaders() });
        if (!response.ok) throw new Error('Session invalid');
        const payload = await response.json();
        user.value = payload.user;
        feedback.value = { text: `Welcome back ${user.value.name}.`, tone: 'success' };
        if (user.value.role === 'parent') {
            await fetchLinkedLearners();
        }
        await generateContent('quiz');
    } catch {
        clearToken();
        user.value = null;
    }
};

const submitAuth = async () => {
    loading.value = true;
    try {
        const endpoint = authMode.value === 'register' ? '/api/v1/auth/register' : '/api/v1/auth/login';
        const body = authMode.value === 'register'
            ? {
                  name: authForm.name.trim(),
                  email: authForm.email.trim(),
                  password: authForm.password,
                  role: authForm.role,
                  age_group: authForm.role === 'learner' ? authForm.ageGroup : null,
              }
            : {
                  email: authForm.email.trim(),
                  password: authForm.password,
              };

        const response = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify(body),
        });
        const payload = await response.json();
        if (!response.ok) {
            throw new Error(payload.message || 'Auth failed');
        }

        setToken(payload.token);
        user.value = payload.user;
        feedback.value = { text: `${authMode.value === 'register' ? 'Registered' : 'Logged in'} successfully.`, tone: 'success' };

        if (user.value.role === 'parent') {
            await fetchLinkedLearners();
        }
        await generateContent('quiz');
    } catch (error) {
        feedback.value = { text: error.message || 'Authentication failed.', tone: 'error' };
    } finally {
        loading.value = false;
    }
};

const logout = async () => {
    if (token.value) {
        await fetch('/api/v1/auth/logout', {
            method: 'POST',
            headers: authHeaders(),
        });
    }
    clearToken();
    user.value = null;
    linkedLearners.value = [];
    activeLearner.value = null;
    learnerProgress.value = null;
    weeklyReport.value = null;
    currentCard.value = null;
    feedback.value = { text: 'Logged out.', tone: 'idle' };
};

const createLearner = async () => {
    if (user.value?.role !== 'parent') return;

    loading.value = true;
    try {
        const createResponse = await fetch('/api/v1/learners', {
            method: 'POST',
            headers: authHeaders(),
            body: JSON.stringify({
                name: learnerForm.name.trim(),
                email: learnerForm.email.trim(),
                password: learnerForm.password,
                age_group: learnerForm.ageGroup,
                grade_level: learnerForm.gradeLevel || null,
            }),
        });
        const createPayload = await createResponse.json();
        if (!createResponse.ok) {
            throw new Error(createPayload.message || 'Could not create learner');
        }

        const learner = createPayload.learner;
        const linkResponse = await fetch(`/api/v1/parents/${user.value.id}/learners/${learner.id}/link`, {
            method: 'POST',
            headers: authHeaders(),
            body: JSON.stringify({ relationship: 'guardian' }),
        });
        if (!linkResponse.ok) {
            throw new Error('Learner created but linking failed');
        }

        linkedLearners.value.unshift(learner);
        if (!activeLearner.value) {
            activeLearner.value = learner;
            await loadLearnerInsights(learner.id);
        }
        learnerForm.name = '';
        learnerForm.email = '';
        learnerForm.password = '';
        learnerForm.gradeLevel = '';
        feedback.value = { text: 'Learner created and linked successfully.', tone: 'success' };
    } catch (error) {
        feedback.value = { text: error.message || 'Learner creation failed.', tone: 'error' };
    } finally {
        loading.value = false;
    }
};

const fetchLinkedLearners = async () => {
    if (!user.value || user.value.role !== 'parent') return;

    try {
        const response = await fetch(`/api/v1/parents/${user.value.id}/learners`, {
            headers: authHeaders(),
        });
        const payload = await response.json();
        if (!response.ok) {
            throw new Error(payload.message || 'Failed to load learners');
        }
        linkedLearners.value = payload.learners ?? [];
        if (linkedLearners.value.length > 0) {
            activeLearner.value = linkedLearners.value[0];
            await loadLearnerInsights(activeLearner.value.id);
        } else {
            activeLearner.value = null;
            learnerProgress.value = null;
            weeklyReport.value = null;
        }
    } catch (error) {
        linkedLearners.value = [];
        feedback.value = { text: error.message || 'Could not load linked learners.', tone: 'error' };
    }
};

const setActiveLearner = async (learner) => {
    activeLearner.value = learner;
    await loadLearnerInsights(learner.id);
};

const loadLearnerInsights = async (learnerId) => {
    if (!user.value || user.value.role !== 'parent') return;

    try {
        const [progressResponse, reportResponse] = await Promise.all([
            fetch(`/api/v1/learners/${learnerId}/progress`, { headers: authHeaders() }),
            fetch(`/api/v1/learners/${learnerId}/reports/weekly`, { headers: authHeaders() }),
        ]);

        const progressPayload = await progressResponse.json();
        const reportPayload = await reportResponse.json();

        if (progressResponse.ok) {
            learnerProgress.value = progressPayload.progress;
        }
        if (reportResponse.ok) {
            weeklyReport.value = reportPayload.report;
        }
    } catch {
        learnerProgress.value = null;
        weeklyReport.value = null;
    }
};

const switchSubject = async (subjectId) => {
    selectedSubject.value = subjectId;
    selectedOption.value = '';
    freeTextAnswer.value = '';
    await generateContent(selectedTab.value);
};

const switchTab = async (tab) => {
    selectedTab.value = tab;
    selectedOption.value = '';
    freeTextAnswer.value = '';
    await generateContent(tab);
};

const generateContent = async (type) => {
    if (!user.value) return;
    loading.value = true;

    try {
        const response = await fetch('/api/learning/generate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({
                subject: selectedSubject.value,
                age_group: effectiveAgeGroup.value,
                type,
                difficulty: streak.value > 6 ? 'hard' : streak.value > 3 ? 'medium' : 'easy',
            }),
        });

        if (!response.ok) throw new Error('Failed to generate content');

        const payload = await response.json();
        currentCard.value = payload.data;
        feedback.value = { text: `New ${type} generated. Source: ${payload.data.source || 'local'}`, tone: 'idle' };
    } catch {
        feedback.value = { text: 'Unable to fetch AI content. Please retry.', tone: 'error' };
    } finally {
        loading.value = false;
    }
};

const submitAttempt = async () => {
    if (!currentCard.value || currentCard.value.type === 'activity') return;

    const answer = selectedTab.value === 'quiz' ? selectedOption.value : freeTextAnswer.value.trim();
    if (!answer) {
        feedback.value = { text: 'Please provide an answer first.', tone: 'error' };
        return;
    }

    loading.value = true;
    try {
        const response = await fetch('/api/learning/attempt', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({
                subject: selectedSubject.value,
                age_group: effectiveAgeGroup.value,
                type: currentCard.value.type,
                prompt: currentCard.value.prompt,
                correct_answer: currentCard.value.answer,
                submitted_answer: answer,
            }),
        });

        if (!response.ok) throw new Error('Attempt submit failed');

        const payload = await response.json();
        const result = payload.data;

        stars.value = Math.max(0, stars.value + result.score_delta);
        streak.value = Math.max(1, streak.value + result.streak_delta);
        attempts.value.unshift({
            subject: selectedSubject.value,
            type: currentCard.value.type,
            isCorrect: result.is_correct,
            status: result.is_correct ? 'Correct' : 'Needs support',
            time: new Date().toLocaleTimeString(),
        });

        feedback.value = {
            text: result.is_correct
                ? `${result.message} Next: ${result.next_recommendation}`
                : `${result.message} Hint: ${result.micro_hint}. Next: ${result.next_recommendation}`,
            tone: result.is_correct ? 'success' : 'error',
        };
    } catch {
        feedback.value = { text: 'Could not submit attempt right now.', tone: 'error' };
    } finally {
        loading.value = false;
    }
};

onMounted(loadSession);
</script>

<template>
    <main class="mx-auto min-h-screen w-full max-w-7xl p-4 sm:p-8">
        <section v-if="!user" class="rounded-[2rem] border border-white/70 bg-white/85 p-6 shadow-xl backdrop-blur-md sm:p-10">
            <div class="grid gap-8 lg:grid-cols-2 lg:items-center">
                <div>
                    <p class="font-bold uppercase tracking-widest text-purple-600">Mindful Learning AI</p>
                    <h1 class="mt-3 font-['Baloo_2'] text-4xl font-bold text-slate-800 sm:text-5xl">Real Auth + Adaptive Learning</h1>
                    <p class="mt-3 text-slate-600">Register/login now uses backend `/api/v1/auth/*` with bearer token session.</p>
                    <div class="mt-6 grid grid-cols-3 gap-3">
                        <img class="rounded-2xl bg-purple-100 p-2" :src="mathImage" alt="Math activity" />
                        <img class="rounded-2xl bg-blue-100 p-2" :src="englishImage" alt="English activity" />
                        <img class="rounded-2xl bg-emerald-100 p-2" :src="scienceImage" alt="Science activity" />
                    </div>
                </div>

                <form class="rounded-3xl border border-slate-200 bg-white p-6 shadow-lg" @submit.prevent="submitAuth">
                    <div class="mb-4 flex gap-2">
                        <button type="button" class="rounded-xl px-4 py-2 font-semibold" :class="authMode === 'register' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600'" @click="authMode = 'register'">Register</button>
                        <button type="button" class="rounded-xl px-4 py-2 font-semibold" :class="authMode === 'login' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600'" @click="authMode = 'login'">Login</button>
                    </div>

                    <h2 class="font-['Baloo_2'] text-3xl font-bold text-slate-800">{{ authMode === 'register' ? 'Create Account' : 'Welcome Back' }}</h2>
                    <div class="mt-4 space-y-3">
                        <input v-if="authMode === 'register'" v-model="authForm.name" type="text" class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none ring-purple-300 focus:ring" placeholder="Name" />
                        <input v-model="authForm.email" type="email" class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none ring-purple-300 focus:ring" placeholder="Email" />
                        <input v-model="authForm.password" type="password" class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none ring-purple-300 focus:ring" placeholder="Password (min 8)" />

                        <template v-if="authMode === 'register'">
                            <select v-model="authForm.role" class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none ring-purple-300 focus:ring">
                                <option value="learner">Learner</option>
                                <option value="parent">Parent</option>
                            </select>
                            <select v-if="authForm.role === 'learner'" v-model="authForm.ageGroup" class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none ring-purple-300 focus:ring">
                                <option v-for="group in ageGroups" :key="group.id" :value="group.id">{{ group.label }}</option>
                            </select>
                        </template>
                    </div>
                    <button class="mt-5 w-full rounded-xl bg-slate-900 px-4 py-3 font-semibold text-white hover:bg-slate-700" :disabled="loading">
                        {{ loading ? 'Please wait...' : authMode === 'register' ? 'Register' : 'Login' }}
                    </button>
                </form>
            </div>
        </section>

        <section v-else class="space-y-6">
            <header class="rounded-[2rem] border border-white/70 bg-white/85 p-6 shadow-xl backdrop-blur-md sm:p-8">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wider text-slate-500">{{ user.role }} dashboard · age {{ effectiveAgeGroup || 'n/a' }}</p>
                        <h2 class="font-['Baloo_2'] text-4xl font-bold text-slate-800">Welcome, {{ user.name }}</h2>
                        <p class="text-slate-600">{{ user.email }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="rounded-2xl bg-purple-100 px-4 py-3 text-center">
                                <p class="text-xs font-semibold text-purple-700">Stars</p>
                                <p class="text-2xl font-black text-purple-800">{{ stars }}</p>
                            </div>
                            <div class="rounded-2xl bg-emerald-100 px-4 py-3 text-center">
                                <p class="text-xs font-semibold text-emerald-700">Streak</p>
                                <p class="text-2xl font-black text-emerald-800">{{ streak }}</p>
                            </div>
                        </div>
                        <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white" @click="logout">Logout</button>
                    </div>
                </div>
            </header>

            <section v-if="user.role === 'parent'" class="grid gap-6 lg:grid-cols-3">
                <article class="rounded-3xl border border-white/70 bg-white/90 p-6 shadow-lg lg:col-span-2">
                    <h3 class="font-['Baloo_2'] text-3xl text-slate-800">Create, Link & Manage Learner</h3>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <input v-model="learnerForm.name" type="text" class="rounded-xl border border-slate-200 px-4 py-3" placeholder="Learner name" />
                        <input v-model="learnerForm.email" type="email" class="rounded-xl border border-slate-200 px-4 py-3" placeholder="Learner email" />
                        <input v-model="learnerForm.password" type="password" class="rounded-xl border border-slate-200 px-4 py-3" placeholder="Learner password" />
                        <select v-model="learnerForm.ageGroup" class="rounded-xl border border-slate-200 px-4 py-3">
                            <option v-for="group in ageGroups" :key="group.id" :value="group.id">{{ group.label }}</option>
                        </select>
                        <input v-model="learnerForm.gradeLevel" type="text" class="rounded-xl border border-slate-200 px-4 py-3 sm:col-span-2" placeholder="Grade level (optional)" />
                    </div>
                    <button class="mt-4 rounded-xl bg-slate-900 px-4 py-2 font-semibold text-white" @click="createLearner" :disabled="loading">
                        Add Learner
                    </button>
                    <div v-if="activeLearner" class="mt-5 rounded-2xl bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Active learner</p>
                        <p class="text-lg font-bold text-slate-800">{{ activeLearner.name }} · {{ activeLearner.age_group }}</p>
                        <p class="text-sm text-slate-500">{{ activeLearner.email }}</p>
                    </div>
                </article>
                <article class="rounded-3xl border border-white/70 bg-white/90 p-6 shadow-lg">
                    <h3 class="font-['Baloo_2'] text-2xl text-slate-800">Linked Learners</h3>
                    <div class="mt-3 space-y-2">
                        <p v-if="linkedLearners.length === 0" class="text-sm text-slate-500">No learners linked yet.</p>
                        <button
                            v-for="learner in linkedLearners"
                            :key="learner.id"
                            class="w-full rounded-xl border p-3 text-left transition"
                            :class="activeLearner?.id === learner.id ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-100 bg-slate-50'"
                            @click="setActiveLearner(learner)"
                        >
                            <p class="text-sm font-semibold" :class="activeLearner?.id === learner.id ? 'text-white' : 'text-slate-700'">{{ learner.name }}</p>
                            <p class="text-xs" :class="activeLearner?.id === learner.id ? 'text-white/80' : 'text-slate-500'">
                                {{ learner.email }} · {{ learner.age_group }}
                            </p>
                        </button>
                    </div>
                </article>
            </section>

            <section v-if="user.role === 'parent' && activeLearner" class="grid gap-6 lg:grid-cols-3">
                <article class="rounded-3xl border border-white/70 bg-white/90 p-6 shadow-lg">
                    <h3 class="font-['Baloo_2'] text-2xl text-slate-800">Learner Progress</h3>
                    <div v-if="learnerProgress" class="mt-3 space-y-2 text-sm text-slate-700">
                        <p><span class="font-semibold">Completion:</span> {{ learnerProgress.completion_percent }}%</p>
                        <p><span class="font-semibold">Accuracy:</span> {{ learnerProgress.accuracy_percent }}%</p>
                        <p><span class="font-semibold">Weekly minutes:</span> {{ learnerProgress.weekly_minutes }}</p>
                        <p><span class="font-semibold">Focus skill:</span> {{ learnerProgress.focus_skill }}</p>
                    </div>
                    <p v-else class="mt-3 text-sm text-slate-500">Loading learner progress...</p>
                </article>
                <article class="rounded-3xl border border-white/70 bg-white/90 p-6 shadow-lg lg:col-span-2">
                    <h3 class="font-['Baloo_2'] text-2xl text-slate-800">Weekly AI Report</h3>
                    <div v-if="weeklyReport" class="mt-3 space-y-3 text-sm text-slate-700">
                        <p>{{ weeklyReport.summary }}</p>
                        <p class="font-semibold text-slate-800">Strengths</p>
                        <ul class="list-disc pl-5">
                            <li v-for="item in weeklyReport.strengths" :key="item">{{ item }}</li>
                        </ul>
                        <p class="font-semibold text-slate-800">Focus Areas</p>
                        <ul class="list-disc pl-5">
                            <li v-for="item in weeklyReport.focus_areas" :key="item">{{ item }}</li>
                        </ul>
                    </div>
                    <p v-else class="mt-3 text-sm text-slate-500">Loading weekly report...</p>
                </article>
            </section>

            <section class="rounded-[2rem] border border-white/70 bg-white/90 p-6 shadow-xl backdrop-blur-md sm:p-8">
                <div class="flex flex-wrap gap-3">
                    <button v-for="subject in subjects" :key="subject.id" class="flex items-center gap-2 rounded-full border px-4 py-2 font-semibold transition"
                        :class="selectedSubject === subject.id ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-400'"
                        @click="switchSubject(subject.id)">
                        <span>{{ subject.icon }}</span>
                        <span>{{ subject.title }}</span>
                    </button>
                </div>

                <div class="mt-6 grid gap-6 lg:grid-cols-5">
                    <div class="rounded-3xl bg-slate-50 p-4 lg:col-span-2">
                        <img :src="currentSubject.image" :alt="currentSubject.title" class="h-44 w-full rounded-2xl object-cover bg-white" />
                        <h3 class="mt-4 font-['Baloo_2'] text-3xl font-bold text-slate-800">{{ currentSubject.title }}</h3>
                        <p class="text-slate-600">Adaptive content for age {{ effectiveAgeGroup }}</p>
                        <div class="mt-4 flex gap-2">
                            <button class="rounded-xl px-4 py-2 font-semibold" :class="selectedTab === 'quiz' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600'" @click="switchTab('quiz')">Quiz</button>
                            <button class="rounded-xl px-4 py-2 font-semibold" :class="selectedTab === 'puzzle' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600'" @click="switchTab('puzzle')">Puzzle</button>
                            <button class="rounded-xl px-4 py-2 font-semibold" :class="selectedTab === 'activity' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600'" @click="switchTab('activity')">Activity</button>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm lg:col-span-3">
                        <p v-if="loading" class="rounded-xl bg-slate-100 px-4 py-3 text-sm text-slate-600">Loading...</p>
                        <template v-else-if="currentCard">
                            <div class="inline-flex rounded-full bg-gradient-to-r px-4 py-2 text-sm font-semibold text-white" :class="currentSubject.accent">
                                {{ currentCard.type }} · {{ currentCard.difficulty }}
                            </div>
                            <h4 class="mt-3 text-2xl font-bold text-slate-800">{{ currentCard.title }}</h4>
                            <p class="mt-2 text-lg text-slate-600">{{ currentCard.prompt }}</p>

                            <div v-if="currentCard.type === 'quiz'" class="mt-4 grid gap-2 sm:grid-cols-3">
                                <button v-for="option in currentCard.options" :key="option" class="rounded-xl border-2 px-4 py-3 font-bold transition"
                                    :class="selectedOption === option ? 'border-purple-500 bg-purple-50 text-purple-700' : 'border-slate-200 text-slate-700 hover:border-purple-300'"
                                    @click="selectedOption = option">
                                    {{ option }}
                                </button>
                            </div>

                            <div v-if="currentCard.type === 'puzzle'" class="mt-4">
                                <input v-model="freeTextAnswer" class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none ring-purple-300 focus:ring" placeholder="Type your puzzle answer" />
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <button v-if="currentCard.type !== 'activity'" class="rounded-xl bg-slate-900 px-4 py-2 font-semibold text-white" @click="submitAttempt">Submit Answer</button>
                                <button class="rounded-xl bg-slate-100 px-4 py-2 font-semibold text-slate-700" @click="generateContent(selectedTab)">
                                    Generate New {{ selectedTab }}
                                </button>
                            </div>
                        </template>

                        <p class="mt-5 rounded-xl px-4 py-3 text-sm font-semibold" :class="{
                            'bg-slate-100 text-slate-600': feedback.tone === 'idle',
                            'bg-emerald-100 text-emerald-700': feedback.tone === 'success',
                            'bg-rose-100 text-rose-700': feedback.tone === 'error',
                        }">{{ feedback.text }}</p>
                    </div>
                </div>
            </section>
        </section>
    </main>
</template>
