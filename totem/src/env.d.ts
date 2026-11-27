/// <reference types="vite/client" />

interface ImportMetaEnv {
    /** Base da API do backend do totem (ex.: https://api.exemplo.com). Vazio = modo demonstração. */
    readonly VITE_TOTEM_API_URL?: string;
}

interface ImportMeta {
    readonly env: ImportMetaEnv;
}

declare module '*.vue' {
    import type { DefineComponent } from 'vue';
    const component: DefineComponent<object, object, unknown>;
    export default component;
}
