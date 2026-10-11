# Imagen del frontend (React 19 + Vite).
# Se sirve con el servidor de desarrollo de Vite para que el evaluador vea la
# aplicacion sin necesidad de compilar. Vite exige --host 0.0.0.0 para quedar
# accesible desde fuera del contenedor.
#
# mirror.gcr.io/library/ es el espejo gratuito de Docker Hub, usado para no
# chocar con el limite de descargas anonimas por IP en GitHub Actions.
#
# Se usa node:20-bookworm-slim (Debian) y NO alpine porque Playwright
# requiere glibc para correr Chromium. Alpine usa musl, lo que rompe
# los E2E. El slim es suficiente para Vite + Node + Chromium.
FROM mirror.gcr.io/library/node:20-bookworm-slim

WORKDIR /app

# Se copian primero los manifiestos para cachear la capa de dependencias.
COPY package.json package-lock.json ./
RUN npm ci

# Dependencias del sistema para Playwright/Chromium. Solo se instalan
# en la imagen de dev/prod (este Dockerfile se usa para ambos via
# docker-compose.yml y docker-compose.dev.yml). Si en el futuro se separa,
# este bloque se mueve a un stage de dev.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libnss3 libnspr4 libatk1.0-0 libatk-bridge2.0-0 libcups2 \
        libdrm2 libdbus-1-3 libxkbcommon0 libxcomposite1 libxdamage1 \
        libxfixes3 libxrandr2 libgbm1 libpango-1.0-0 libcairo2 libasound2 \
    && rm -rf /var/lib/apt/lists/*

COPY . .

EXPOSE 5173

CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0", "--port", "5173"]
