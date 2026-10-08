FROM node:22-alpine

RUN apk add --no-cache chromium
WORKDIR /tests
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts --no-audit --no-fund
CMD ["node", "--version"]
