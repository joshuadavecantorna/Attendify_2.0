<script setup lang="ts">
import { Toaster as Sonner } from 'vue-sonner';
</script>

<template>
  <Sonner
    position="top-center"
    :duration="3000"
    closeButton
    richColors
    :toastOptions="{
      classNames: {
        toast: 'group toast group-[.toaster]:bg-background group-[.toaster]:text-foreground group-[.toaster]:border-border group-[.toaster]:shadow-lg',
        description: 'group-[.toast]:text-muted-foreground',
        actionButton: 'group-[.toast]:bg-primary group-[.toast]:text-primary-foreground',
        cancelButton: 'group-[.toast]:bg-muted group-[.toast]:text-muted-foreground',
        error: 'group-[.toaster]:!bg-destructive group-[.toaster]:!text-destructive-foreground group-[.toaster]:!border-destructive',
        success: 'group-[.toaster]:!bg-green-600 group-[.toaster]:!text-white group-[.toaster]:!border-green-700',
        warning: 'group-[.toaster]:!bg-yellow-600 group-[.toaster]:!text-white group-[.toaster]:!border-yellow-700',
        info: 'group-[.toaster]:!bg-blue-600 group-[.toaster]:!text-white group-[.toaster]:!border-blue-700',
      },
    }"
  />
</template>
