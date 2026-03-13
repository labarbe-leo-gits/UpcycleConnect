# Build stage
FROM golang:1.25.2-alpine AS builder

WORKDIR /src

# Cache dependencies
COPY go.mod go.sum ./
RUN go mod download

# Copy source
COPY . .

# Build the API binary (build only the main package)
RUN CGO_ENABLED=0 GOOS=linux go build -o /usr/local/bin/api .

# Runtime stage
FROM gcr.io/distroless/static-debian13

WORKDIR /app

# Copy the built binary
COPY --from=builder /usr/local/bin/api /app/api

# Optionally include .env so the binary can load it when running in container
COPY --from=builder /src/.env /app/.env

EXPOSE 9999

ENTRYPOINT ["/app/api"]
