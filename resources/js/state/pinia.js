import { reactive } from 'vue';

const authState = reactive({
    currentUser: null,
});

const layoutState = reactive({
    mode: 'light',
    changeMode(mode) {
        this.mode = mode;
        document.body.setAttribute('data-bs-theme', mode === 'dark' ? 'dark' : 'light');
        sessionStorage.setItem('is_visited', mode === 'dark' ? 'dark' : 'default');
    },
});

export function useAuthStore() {
    return authState;
}

export function useLayoutStore() {
    return layoutState;
}
