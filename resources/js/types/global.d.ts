import type { SharedData } from '@/types';

declare global {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: SharedData;
    }
}
