export interface ChatMessage {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    timestamp: Date;
    isLoading?: boolean;
}

export interface ChatState {
    isOpen: boolean;
    messages: ChatMessage[];
    isLoading: boolean;
    error: string | null;
}

export interface ChatResponse {
    success: boolean;
    response: string;
    data?: any;
    error?: string;
}

export interface SuggestedQuery {
    icon: string;
    text: string;
    query: string;
}
