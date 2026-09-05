<script lang="ts">
/**
 * The button that opens the help drawer. Pair it with HelpDrawer, or call
 * openCodex() from anywhere instead; the drawer listens for both.
 */

/** The keyboard shortcut HelpDrawer listens for, in case the button wants to show it. */
export const HELP_SHORTCUT = 'Ctrl+/'
</script>

<script setup lang="ts">
withDefaults(defineProps<{ label?: string }>(), { label: 'Help' })

const emit = defineEmits<{ click: [] }>()
</script>

<template>
  <button type="button" class="codex-help-button" :aria-label="label" @click="emit('click')">
    {{ label }}
  </button>
</template>
