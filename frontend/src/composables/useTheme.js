import { ref, watchEffect } from 'vue'

const STORAGE_KEY = 'lendflow_theme' // 'light' | 'dark' | 'system'

const theme = ref(localStorage.getItem(STORAGE_KEY) || 'system')
const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)')

function applyTheme() {
  const isDark = theme.value === 'dark' || (theme.value === 'system' && systemPrefersDark.matches)
  document.documentElement.classList.toggle('dark', isDark)
}

watchEffect(applyTheme)
systemPrefersDark.addEventListener('change', applyTheme)

export function useTheme() {
  function setTheme(value) {
    theme.value = value
    localStorage.setItem(STORAGE_KEY, value)
  }

  return { theme, setTheme }
}
