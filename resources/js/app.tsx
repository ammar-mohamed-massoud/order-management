import { createInertiaApp } from "@inertiajs/react";

const appName = import.meta.env.VITE_APP_NAME || "Laravel";

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === "welcome":
                return null;
        }
    },
    strictMode: true,
    withApp(app) {
        return { app };
    },
    progress: {
        color: "#4B5563",
    },
});
