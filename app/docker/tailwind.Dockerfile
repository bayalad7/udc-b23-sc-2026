FROM debian:bookworm-slim

# Nota: el binario standalone de Tailwind está enlazado contra glibc, no
# corre sobre musl (Alpine) — de ahí "no such file or directory" al
# ejecutarlo en Alpine aunque el archivo sí exista. Por eso Debian, no Alpine.
RUN apt-get update && apt-get install -y --no-install-recommends ca-certificates curl && \
    rm -rf /var/lib/apt/lists/* && \
    arch="$(uname -m)" && \
    case "$arch" in \
        x86_64)  bin="tailwindcss-linux-x64" ;; \
        aarch64) bin="tailwindcss-linux-arm64" ;; \
        armv7l)  bin="tailwindcss-linux-armv7" ;; \
        *) echo "Arquitectura no soportada: $arch" && exit 1 ;; \
    esac && \
    curl -sLo /usr/local/bin/tailwindcss \
        "https://github.com/tailwindlabs/tailwindcss/releases/latest/download/${bin}" && \
    chmod +x /usr/local/bin/tailwindcss

WORKDIR /var/www/html

ENTRYPOINT ["tailwindcss"]
# --watch=always: sin esto, el CLI deja de vigilar en cuanto detecta que el
# stdin está cerrado — que es siempre el caso en un contenedor en segundo
# plano (docker compose up -d), así que salía casi de inmediato sin compilar.
CMD ["-i", "/var/www/html/assets/css/input.css", "-o", "/var/www/html/assets/css/tailwind.css", "--watch=always"]
