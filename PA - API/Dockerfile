# Build stage
FROM golang:1.25.2-alpine AS builder

WORKDIR /src

COPY go.mod go.sum ./
RUN go mod download

# Copy source
COPY . .

RUN CGO_ENABLED=0 GOOS=linux go build -o /usr/local/bin/api .

# Runtime stage
FROM gcr.io/distroless/static-debian13

WORKDIR /app

COPY --from=builder /usr/local/bin/api /app/api

EXPOSE 9999

ENTRYPOINT ["/app/api"]
