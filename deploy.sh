#!/bin/bash
# Deploy file su server web via FTPS — /RooT-Camp
#
# Configurazione: crea un file .deploy-config nella root del progetto con:
#   FTP_HOST=ftp.example.com
#   FTP_USER=utente
#   FTP_PASS=password
#   FTP_REMOTE_DIR=/   (o /public_html, ecc.)
#
# Uso:
#   ./deploy.sh index.html scripts/home.js
#   ./deploy.sh --all          (carica tutto il progetto, escludendo file protetti)
#
# File MAI deployati (vedi PROTECTED_FILES + esclusioni di --all):
#   .env, .env.example, .deploy-config, deploy.sh, *.sql, *.sqlite, *.log,
#   CLAUDE.md, *standalone* (snapshot esportati), scraps/, .git/
#
# Rename in upload: i file .htaccess sono versionati e sincronizzati come
# _.htaccess (Nextcloud rifiuta i file che iniziano con un punto e blocca la
# sync). Sul server però devono chiamarsi .htaccess per essere attivi, quindi
# OGNI _.htaccess viene caricato come .htaccess (root, inc/, bin/, ...).
# Puoi anche passare ".htaccess" come argomento: il sorgente locale usato è
# l'_.htaccess corrispondente.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
CONFIG_FILE="$SCRIPT_DIR/.deploy-config"

if [[ ! -f "$CONFIG_FILE" ]]; then
    echo "Errore: file $CONFIG_FILE non trovato."
    echo "Crea il file con:"
    echo "  FTP_HOST=ftp.example.com"
    echo "  FTP_USER=utente"
    echo "  FTP_PASS=password"
    echo "  FTP_REMOTE_DIR=/public_html/bot"
    exit 1
fi

source "$CONFIG_FILE"

# Rimuovi trailing slash da FTP_REMOTE_DIR, gestisci "/" come vuoto
FTP_REMOTE_DIR="${FTP_REMOTE_DIR%/}"

# Mappa il path locale (relativo al progetto) al path remoto.
# Unica regola: _.htaccess -> .htaccess (in qualsiasi directory).
remote_rel() {
    local rel="$1" dir base
    dir=$(dirname "$rel")
    base=$(basename "$rel")
    [[ "$base" == "_.htaccess" ]] && base=".htaccess"
    if [[ "$dir" == "." ]]; then
        printf '%s\n' "$base"
    else
        printf '%s/%s\n' "$dir" "$base"
    fi
}

for var in FTP_HOST FTP_USER FTP_PASS; do
    if [[ -z "${!var:-}" ]]; then
        echo "Errore: $var non definito in $CONFIG_FILE"
        exit 1
    fi
done

if [[ $# -eq 0 ]]; then
    echo "Uso: $0 file1 [file2 ...]"
    echo "      $0 --all"
    exit 1
fi

# File protetti: non vengono mai caricati (segreti, dump DB, artefatti locali).
# config.php NON è qui: ora contiene solo costanti applicative, i segreti stanno in .env.
PROTECTED_FILES=(
    ".env"
    ".env.example"
    ".deploy-config"
    "deploy.sh"
    "schema.sql"
    "seed-2026.sql"
    "CLAUDE.md"
)

# Costruisci lista file
FILES=()
if [[ "$1" == "--all" ]]; then
    while IFS= read -r -d '' f; do
        FILES+=("$f")
    done < <(find "$SCRIPT_DIR" -type f \
        ! -path '*/.git/*' \
        ! -path '*/scraps/*' \
        ! -name '.env' \
        ! -name '.env.example' \
        ! -name '.deploy-config' \
        ! -name 'deploy.sh' \
        ! -name '*.sql' \
        ! -name '*.sqlite' \
        ! -name '*.log' \
        ! -name 'CLAUDE.md' \
        ! -name '*standalone*' \
        -print0)
else
    for f in "$@"; do
        # Consenti di passare ".htaccess": il sorgente locale è "_.htaccess".
        if [[ "$(basename "$f")" == ".htaccess" ]]; then
            f="$(dirname "$f")/_.htaccess"
            f="${f#./}"
        fi
        basename=$(basename "$f")
        skip=false
        for p in "${PROTECTED_FILES[@]}"; do
            if [[ "$basename" == "$p" ]]; then
                echo "BLOCCATO: '$f' è un file protetto, salto."
                skip=true
                break
            fi
        done
        $skip && continue

        if [[ -f "$SCRIPT_DIR/$f" ]]; then
            FILES+=("$SCRIPT_DIR/$f")
        elif [[ -f "$f" ]]; then
            FILES+=("$f")
        else
            echo "Attenzione: file '$f' non trovato, salto."
        fi
    done
fi

if [[ ${#FILES[@]} -eq 0 ]]; then
    echo "Nessun file da caricare."
    exit 1
fi

echo "Caricamento di ${#FILES[@]} file su $FTP_HOST:$FTP_REMOTE_DIR ..."

# Genera comandi FTP
FTP_COMMANDS=""
for filepath in "${FILES[@]}"; do
    # Calcola path relativo al progetto (e quello remoto, con eventuale rename)
    rel="${filepath#$SCRIPT_DIR/}"
    rrel="$(remote_rel "$rel")"
    remote_dir="$FTP_REMOTE_DIR/$(dirname "$rrel")"
    FTP_COMMANDS+="mkdir $remote_dir
"
    FTP_COMMANDS+="put $filepath $FTP_REMOTE_DIR/$rrel
"
done

curl --ftp-create-dirs -s -S \
    --user "$FTP_USER:$FTP_PASS" \
    "ftp://$FTP_HOST" \
    -Q "dummy" 2>/dev/null || true

# Carica ogni file con curl
ERRORS=0
for filepath in "${FILES[@]}"; do
    rel="${filepath#$SCRIPT_DIR/}"
    remote_path="$FTP_REMOTE_DIR/$(remote_rel "$rel")"
    echo -n "  $rel -> $remote_path ... "
    if curl -s -S --ssl-reqd -k --ftp-create-dirs \
        --user "$FTP_USER:$FTP_PASS" \
        -T "$filepath" \
        "ftp://$FTP_HOST$remote_path"; then
        echo "OK"
    else
        echo "ERRORE"
        ERRORS=$((ERRORS + 1))
    fi
done

if [[ $ERRORS -eq 0 ]]; then
    echo "Deploy completato: ${#FILES[@]} file caricati."
else
    echo "Deploy completato con $ERRORS errori su ${#FILES[@]} file."
    exit 1
fi
