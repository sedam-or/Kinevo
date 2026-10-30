import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import { App } from "./app/providers";
import "./styles/base.css";
import { ComponentsDemo } from "./app/components-demo";
import { ToastProvider } from "./components/Toast";
import "./i18n";

const root = document.getElementById("root");

if (!root) {
  throw new Error("Missing application root element");
}

createRoot(root).render(
  <StrictMode>
    <ToastProvider>
      {import.meta.env.DEV && window.location.pathname === "/__components" ? (
        <ComponentsDemo />
      ) : (
        <App />
      )}
    </ToastProvider>
  </StrictMode>,
);
