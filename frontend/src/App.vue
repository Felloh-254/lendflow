<script setup>
import { useRoute } from 'vue-router'
import AppShell from './components/AppShell.vue'

const route = useRoute()
</script>

<template>
  <RouterView v-if="route.meta.guestOnly" />
  <AppShell v-else>
    <RouterView />
  </AppShell>
</template>
