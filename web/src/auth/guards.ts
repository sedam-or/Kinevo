import type { AuthManager } from "./AuthManager";

export function safeNext(raw: string | null | undefined): string {
  if (!raw?.startsWith("/") || raw.startsWith("//") || raw.includes("\\")) return "/today";
  try {
    const url = new URL(raw, "https://nivaras.invalid");
    if (url.origin !== "https://nivaras.invalid") return "/today";
    return `${url.pathname}${url.search}${url.hash}`;
  } catch {
    return "/today";
  }
}

export async function redirectIfAnonymous(
  manager: AuthManager,
  next: string,
): Promise<string | undefined> {
  await manager.restore();
  if (manager.state === "authenticated") return undefined;
  return `/login?next=${encodeURIComponent(safeNext(next))}`;
}

export function isPublicAuthPath(path: string): boolean {
  return (
    path === "/login" ||
    path === "/register" ||
    path.startsWith("/reset/") ||
    path === "/session/revoked" ||
    path === "/setup"
  );
}
