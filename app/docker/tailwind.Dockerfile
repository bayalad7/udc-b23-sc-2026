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
#
# --poll=1000: en un bind mount de Windows (Docker Desktop) los eventos de
# inotify del host no llegan al contenedor, así que el watch se quedaba
# "corriendo" sin recompilar nunca — se detectó con el CSS parado casi 4
# horas mientras se editaban los .php. Sondear cada segundo cuesta muy poco
# en un proyecto de este tamaño y es la diferencia entre que el CSS se
# regenere solo o subir la página con clases sin generar.
CMD ["-i", "/var/www/html/assets/css/input.css", "-o", "/var/www/html/assets/css/tailwind.css", "--watch=always", "--poll=1000"]
