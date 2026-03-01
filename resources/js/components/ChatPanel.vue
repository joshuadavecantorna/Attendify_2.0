<template>
  <div class="chat-panel">
    <div class="messages" ref="messagesEl">
      <div v-for="msg in store.messages" :key="msg.id" :class="['message', msg.role]">
        <div class="bubble">
          <template v-if="msg.role === 'user'">You: </template>
          <template v-else-if="msg.role === 'assistant'">Bot: </template>
          <template v-else-if="msg.role === 'error'">Error: </template>
          <pre class="msg-text">{{ msg.text }}</pre>
          <div v-if="msg.streaming" class="streaming">● Streaming...</div>
          <div v-if="msg.error" class="error">{{ msg.error }}</div>
          <div v-if="msg.role === 'error'" class="retry">
            <button @click="onRetry(msg.id)">Retry</button>
          </div>
        </div>
      </div>
    </div>

    <div class="composer">
      <textarea v-model="input" @keydown.enter.exact.prevent="onSend" placeholder="Ask the AttendSynx bot..." rows="2"></textarea>
      <div class="controls">
        <label><input type="checkbox" v-model="useStream" /> Stream response</label>
        <button @click="onSend" :disabled="sending">Send</button>
        <button @click="onCancel" v-if="store.streaming">Cancel</button>
        <button @click="onClear">Clear</button>
      </div>
      <div v-if="store.lastError" class="global-error">Error: {{ store.lastError }}</div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent, ref, onMounted, watch, nextTick } from 'vue';
import { useChatbotStore } from '@/stores/useChatbot';

export default defineComponent({
  name: 'ChatPanel',
  setup() {
    const store = useChatbotStore();
    const input = ref('');
    const useStream = ref(true);
    const sending = ref(false);
    const messagesEl = ref<HTMLElement | null>(null);

    async function onSend() {
      if (!input.value.trim()) return;
      sending.value = true;
      try {
        await store.sendMessage(input.value.trim(), useStream.value);
        input.value = '';
        await nextTick();
        scrollToBottom();
      } finally {
        sending.value = false;
      }
    }

    function onCancel() {
      store.cancelStream();
    }

    function onClear() {
      store.clear();
    }

    function onRetry(assistantId: string) {
      store.retry(assistantId);
    }

    function scrollToBottom() {
      if (!messagesEl.value) return;
      messagesEl.value.scrollTop = messagesEl.value.scrollHeight;
    }

    onMounted(() => {
      watch(
        () => store.messages.length,
        () => {
          nextTick(scrollToBottom);
        }
      );
    });

    return {
      store,
      input,
      useStream,
      onSend,
      onCancel,
      onClear,
      onRetry,
      sending,
      messagesEl,
    };
  },
});
</script>

<style scoped>
.chat-panel {
  display: flex;
  flex-direction: column;
  gap: 8px;
  border: 1px solid #e5e7eb;
  padding: 12px;
  border-radius: 8px;
  max-width: 720px;
  margin: 0 auto;
}
.messages {
  max-height: 420px;
  overflow: auto;
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 8px;
  background: #fafafa;
  border-radius: 6px;
}
.message {
  display: flex;
}
.message .bubble {
  background: white;
  padding: 8px 10px;
  border-radius: 6px;
  box-shadow: 0 1px 2px rgba(0,0,0,0.04);
}
.message.user .bubble {
  background: #e6f0ff;
  align-self: flex-end;
}
.message.assistant .bubble {
  background: #f3f4f6;
}
.message.error .bubble {
  background: #fff4f4;
  border: 1px solid #fecaca;
}
.msg-text {
  white-space: pre-wrap;
  margin: 0;
}
.streaming {
  color: #2563eb;
  font-size: 12px;
  margin-top: 6px;
}
.error {
  color: #b91c1c;
  font-size: 12px;
  margin-top: 6px;
}
.composer {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.controls {
  display: flex;
  gap: 8px;
  align-items: center;
}
.global-error {
  color: #b91c1c;
}
</style>
