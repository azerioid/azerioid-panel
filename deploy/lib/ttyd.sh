#!/usr/bin/env bash
# Install pinned ttyd binary (panel bootstrap dependency for vhost terminals).
set -euo pipefail

TTYD_VERSION="${TTYD_VERSION:-1.7.7}"
TTYD_INSTALL_DIR="${TTYD_INSTALL_DIR:-/usr/local/bin}"
TTYD_BIN="${TTYD_INSTALL_DIR}/ttyd"

install_ttyd() {
    if [[ -x "${TTYD_BIN}" ]]; then
        if "${TTYD_BIN}" --version 2>/dev/null | grep -q "${TTYD_VERSION}"; then
            echo "==> ttyd ${TTYD_VERSION} already installed at ${TTYD_BIN}"
            return 0
        fi
    fi

    local arch asset sha
    arch="$(uname -m)"
    case "${arch}" in
        x86_64|amd64) asset="ttyd.x86_64"; sha="8a217c968aba172e0dbf3f34447218dc015bc4d5e59bf51db2f2cd12b7be4f55" ;;
        aarch64|arm64) asset="ttyd.aarch64"; sha="b38acadd89d1d396a0f5649aa52c539edbad07f4bc7348b27b4f4b7219dd4165" ;;
        *) echo "Unsupported architecture for ttyd bootstrap: ${arch}" >&2; exit 1 ;;
    esac

    local url="https://github.com/tsl0922/ttyd/releases/download/${TTYD_VERSION}/${asset}"
    local tmp
    tmp="$(mktemp /tmp/ttyd.XXXXXX)"
    echo "==> Installing ttyd ${TTYD_VERSION} (${asset})"
    curl -fsSL "${url}" -o "${tmp}"
    echo "${sha}  ${tmp}" | sha256sum -c -
    install -d -m 0755 "${TTYD_INSTALL_DIR}"
    install -m 0755 "${tmp}" "${TTYD_BIN}"
    rm -f "${tmp}"
    "${TTYD_BIN}" --version
}
