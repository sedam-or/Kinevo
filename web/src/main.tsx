import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import { App } from "./app/providers";
import "./styles/tokens.css";
import "./styles/fonts.css";
import "./styles/base.css";

const root = document.getElementById("root");

if (!root) {
  throw new Error("Missing application root element");
}

createRoot(root).render(
  <StrictMode>
    <App />
  </StrictMode>,
);
