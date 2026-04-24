# Mindful Learning: Production Blueprint

## 1) Product Core Principle

Build a **learning intelligence platform**, not a quiz app.

Primary differentiator:
- Track **micro-skills**
- Detect **mistake patterns**
- Adapt session content in real time
- Explain recommendations to parents in simple language

---

## 2) Data Model (Schema)

### `users`
Purpose: Auth + account identity.

Key fields:
- `id`
- `name`
- `email` (unique)
- `password`
- `role` (`parent`, `learner`, `teacher`, `admin`)
- `age_group` (`4-6`, `7-9`, `10-12`, nullable)
- timestamps

### `learner_profiles`
Purpose: learner-specific adaptive attributes.

Key fields:
- `id`
- `user_id` (fk users, unique)
- `grade_level` (nullable)
- `reading_level` (`early`, `basic`, `fluent`)
- `pace_level` (`slow`, `steady`, `fast`)
- `confidence_level` (0-100)
- `attention_window_minutes` (default 10)
- `preferred_language` (default `en`)
- timestamps

### `parent_learner_links`
Purpose: many-to-many parent-child mapping.

Key fields:
- `id`
- `parent_user_id` (fk users)
- `learner_user_id` (fk users)
- `relationship` (`mother`, `father`, `guardian`, `other`)
- timestamps

Unique index:
- (`parent_user_id`, `learner_user_id`)

### `subjects`
Purpose: canonical subject metadata.

Key fields:
- `id`
- `code` (`math`, `english`, `science`) unique
- `name`
- timestamps

### `skills`
Purpose: micro-skill graph nodes.

Key fields:
- `id`
- `subject_id` (fk subjects)
- `code` (example: `math.fractions.compare`)
- `name`
- `description`
- `difficulty_band` (`foundation`, `intermediate`, `advanced`)
- `age_group_min`
- `age_group_max`
- timestamps

Unique index:
- (`subject_id`, `code`)

### `skill_prerequisites`
Purpose: prerequisite edges for skill graph.

Key fields:
- `id`
- `skill_id` (fk skills)
- `prerequisite_skill_id` (fk skills)
- timestamps

Unique index:
- (`skill_id`, `prerequisite_skill_id`)

### `content_items`
Purpose: generated/curated activities.

Key fields:
- `id`
- `subject_id` (fk subjects)
- `skill_id` (fk skills, nullable)
- `type` (`quiz`, `puzzle`, `activity`)
- `age_group`
- `difficulty` (`easy`, `medium`, `hard`)
- `title`
- `prompt` (text)
- `options_json` (json, nullable)
- `answer_key` (nullable text)
- `hint_text` (nullable text)
- `explanation_text` (nullable text)
- `source` (`openai`, `local`, `teacher`)
- `safety_version` (nullable)
- timestamps

Indexes:
- (`subject_id`, `type`, `age_group`, `difficulty`)
- (`skill_id`, `age_group`)

### `learning_sessions`
Purpose: session container.

Key fields:
- `id` (uuid preferred)
- `learner_user_id` (fk users)
- `started_at`
- `ended_at` (nullable)
- `planned_mix_json` (example: `{"practice":0.6,"confidence":0.25,"stretch":0.15}`)
- `status` (`active`, `completed`, `abandoned`)
- timestamps

### `attempts`
Purpose: answer-level outcome records.

Key fields:
- `id`
- `learning_session_id` (uuid fk learning_sessions)
- `learner_user_id` (fk users)
- `content_item_id` (fk content_items)
- `subject_id` (fk subjects)
- `skill_id` (fk skills, nullable)
- `submitted_answer` (text)
- `is_correct` (bool)
- `score_delta` (int)
- `streak_delta` (int)
- `time_spent_ms` (int)
- `hint_count` (int default 0)
- `confidence_self_rating` (1-5 nullable)
- `ai_feedback_json` (json nullable)
- timestamps

Indexes:
- (`learner_user_id`, `created_at`)
- (`learner_user_id`, `subject_id`, `skill_id`)

### `learner_skill_states`
Purpose: current mastery state per micro-skill.

Key fields:
- `id`
- `learner_user_id` (fk users)
- `skill_id` (fk skills)
- `mastery_score` (0-1 decimal)
- `confidence_score` (0-1 decimal)
- `last_seen_at`
- `next_review_at` (spaced repetition)
- `attempts_count`
- `correct_count`
- timestamps

Unique index:
- (`learner_user_id`, `skill_id`)

### `recommendation_decisions`
Purpose: explainable recommendation audit trail.

Key fields:
- `id`
- `learner_user_id` (fk users)
- `subject_id` (fk subjects)
- `recommended_content_item_id` (fk content_items)
- `decision_reason_json` (json)
- `algorithm_version`
- `was_served` (bool)
- `was_completed` (bool nullable)
- timestamps

### `weekly_reports`
Purpose: parent-facing narrative summaries.

Key fields:
- `id`
- `learner_user_id` (fk users)
- `week_start_date`
- `week_end_date`
- `report_json` (json)
- `generated_by` (`ai`, `system`)
- timestamps

Unique index:
- (`learner_user_id`, `week_start_date`, `week_end_date`)

---

## 3) Service Boundaries (Laravel)

### `AuthService`
- registration/login/logout
- role checks and ownership checks (parent -> learner)

### `ContentGenerationService`
- generate safe content via OpenAI
- validate strict JSON schema
- save as `content_items`
- fallback to curated library

### `AttemptEvaluationService`
- evaluate answer correctness
- update score/streak
- request AI coaching feedback

### `SkillStateService`
- update `learner_skill_states` from attempts
- adjust mastery/confidence
- schedule `next_review_at`

### `RecommendationService`
- next-best-content selection
- inputs: mastery gaps, mistake taxonomy, fatigue/attention, age group
- output: ranked recommendations + explainable reasons

### `SessionOrchestratorService`
- build 10-min plan mix:
  - practice (60%)
  - confidence win (25%)
  - stretch challenge (15%)
- keep session adaptive every few attempts

### `ReportService`
- generate weekly parent reports
- plain-language summaries + action plan

### `SafetyPolicyService`
- prompt policy enforcement
- banned content checks
- age-safe wording constraints

---

## 4) API Contracts (v1)

Base: `/api/v1`

### Auth

`POST /auth/register`
- body:
```json
{
  "name": "Ayesha",
  "email": "ayesha@example.com",
  "password": "secret123",
  "role": "parent",
  "age_group": null
}
```
- response:
```json
{
  "user": { "id": 1, "name": "Ayesha", "role": "parent" },
  "token": "..."
}
```

`POST /auth/login`
`POST /auth/logout`
`GET /auth/me`

### Learner profile

`POST /learners`
`GET /learners/{id}`
`PATCH /learners/{id}`
`POST /parents/{parentId}/learners/{learnerId}/link`

### Session + content

`POST /learning/sessions`
- body:
```json
{
  "learner_id": 12,
  "subject_focus": ["math", "english"],
  "duration_minutes": 10
}
```

`POST /learning/generate`
- body:
```json
{
  "subject": "math",
  "age_group": "7-9",
  "type": "quiz",
  "difficulty": "medium",
  "skill_code": "math.addition.2digit"
}
```
- response:
```json
{
  "data": {
    "id": 982,
    "type": "quiz",
    "title": "Speed Add",
    "prompt": "14 + 8 = ?",
    "options": ["20", "22", "24"],
    "answer": "22",
    "hint": "Break 8 into 6 and 2.",
    "source": "openai"
  }
}
```

`POST /learning/attempts`
- body:
```json
{
  "session_id": "uuid",
  "content_item_id": 982,
  "submitted_answer": "22",
  "time_spent_ms": 14000,
  "hint_count": 1
}
```
- response:
```json
{
  "data": {
    "is_correct": true,
    "score_delta": 10,
    "streak_delta": 1,
    "feedback": {
      "message": "Great thinking!",
      "micro_hint": null,
      "next_recommendation": "Try one puzzle on the same skill."
    },
    "skill_state": {
      "skill_code": "math.addition.2digit",
      "mastery_score": 0.66
    }
  }
}
```

### Recommendations

`GET /learners/{id}/recommendations?limit=5`
- returns ranked list with reason:
```json
{
  "data": [
    {
      "content_item_id": 991,
      "subject": "math",
      "type": "puzzle",
      "reason": "Recent attempts show confusion in carrying over."
    }
  ]
}
```

### Parent reports

`GET /learners/{id}/reports/weekly?week_start=2026-04-20`

Response fields:
- progress summary
- top improving skills
- weak skills with reasons
- next week 20-minute plan

---

## 5) Event Model (for Analytics + AI)

Emit immutable events:
- `session_started`
- `content_served`
- `hint_requested`
- `attempt_submitted`
- `feedback_rendered`
- `session_completed`

Event payload standard:
- `event_id`
- `event_type`
- `occurred_at`
- `learner_id`
- `session_id`
- `subject`
- `skill_code`
- `content_item_id`
- `metadata_json`

Store in `learning_events` (append-only) for future model tuning.

---

## 6) Recommendation Algorithm (v1 -> v2)

### v1 (deterministic)
Score each candidate:
- `gap_score` (low mastery => higher)
- `freshness_score` (recently missed => higher)
- `fatigue_penalty` (long hard streak => lower)
- `age_fit_score` (strict match)
- `variety_score` (avoid repetition)

Final:
- `rank = 0.4*gap + 0.25*freshness + 0.15*age_fit + 0.1*variety - 0.1*fatigue`

### v2 (bandit/IRT-lite)
- contextual bandit per learner segment
- optimize for mastery gain + session completion

---

## 7) Safety + Quality Requirements

- Strict JSON schema validation for LLM outputs.
- Age-group vocabulary guardrails.
- Reject or regenerate unsafe/off-curriculum outputs.
- Store prompt/response hashes for audit.
- Feature-flag AI provider usage.

---

## 8) 4-Phase Implementation Plan

### Phase 1: Foundation (1-2 weeks)
- full auth + role-based access
- migrations for core tables
- basic learner/parent linking
- event tracking skeleton

### Phase 2: Adaptive Core (2-3 weeks)
- skill graph + skill states
- attempt evaluation + taxonomy
- deterministic recommendation engine

### Phase 3: AI Intelligence (2 weeks)
- OpenAI structured generation
- AI coaching feedback with guardrails
- fallback and retry strategy

### Phase 4: Parent Value Layer (1-2 weeks)
- weekly reports
- explainable recommendation UI
- outcome dashboard (mastery/time/confidence)

---

## 9) Metrics That Matter

- D7/D30 learner retention
- sessions per learner per week
- mastery gain per 60 minutes
- time-to-mastery per skill
- parent weekly report open rate
- recommendation acceptance rate

If these metrics improve, product is truly standing out.
