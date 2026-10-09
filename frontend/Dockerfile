# Imagen del frontend (React 19 + Vite).
# Se sirve con el servidor de desarrollo de Vite para que el evaluador vea la
# aplicacion sin necesidad de compilar. Vite exige --host 0.0.0.0 para quedar
# accesible desde fuera del contenedor.

FROM node:20-alpine

WORKDIR /app

# Se copian primero los manifiestos para cachear la capa de dependencias.
COPY package.json package-lock.json ./
RUN npm ci

COPY . .

EXPOSE 5173

CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0", "--port", "5173"]
