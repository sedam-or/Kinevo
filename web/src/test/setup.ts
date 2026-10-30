import "@testing-library/jest-dom/vitest";
import { cleanup } from "@testing-library/react";
import { setupServer } from "msw/node";
import { afterAll, afterEach, beforeAll } from "vitest";
import { authHandlers } from "./handlers/auth";

export const server = setupServer(authHandlers.refresh);

Object.defineProperty(window, "scrollTo", { value: () => {}, configurable: true });

beforeAll(() => server.listen());

afterEach(() => {
  cleanup();
  server.resetHandlers();
});

afterAll(() => server.close());
