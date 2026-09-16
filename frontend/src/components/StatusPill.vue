<script setup>
import { computed } from 'vue'

const props = defineProps({
  status: { type: String, required: true },
})

// One mapping, used everywhere a status appears — new statuses only need
// a single new entry here, never a scattered set of ad hoc color choices
// per view.
const STATUS_STYLES = {
  // neutral / in-progress
  draft: 'bg-slate-500/10 text-slate-500',
  pending: 'bg-slate-500/10 text-slate-500',
  submitted: 'bg-info/10 text-info',
  under_review: 'bg-info/10 text-info',
  processing: 'bg-info/10 text-info',
  partially_paid: 'bg-warning/10 text-warning',
  pending_disbursement: 'bg-warning/10 text-warning',
  // positive
  approved: 'bg-success/10 text-success',
  active: 'bg-success/10 text-success',
  completed: 'bg-success/10 text-success',
  paid: 'bg-success/10 text-success',
  // negative
  rejected: 'bg-danger/10 text-danger',
  cancelled: 'bg-danger/10 text-danger',
  overdue: 'bg-danger/10 text-danger',
  defaulted: 'bg-danger/10 text-danger',
  failed: 'bg-danger/10 text-danger',
  suspended: 'bg-danger/10 text-danger',
}

const label = computed(() => props.status.replaceAll('_', ' '))
const classes = computed(() => STATUS_STYLES[props.status] || 'bg-slate-500/10 text-slate-500')
</script>

<template>
  <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize" :class="classes">
    {{ label }}
  </span>
</template>
