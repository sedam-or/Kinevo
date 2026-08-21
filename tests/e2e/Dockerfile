# Kinevo E2E runner image (rescue R1). Based on the official Playwright image
# whose bundled browsers match @playwright/test; the test source, config, and
# Node deps (with container-native browser binaries) are baked in so the image
# is self-contained. The SPA is reached over the host network at 127.0.0.1:8000.
FROM mcr.microsoft.com/playwright:v1.62.1-jammy

WORKDIR /e2e

COPY package.json package-lock.json ./
COPY playwright.config.ts ./
COPY tests ./tests

RUN npm install --no-audit --no-fund

CMD ["npx", "playwright", "test"]
