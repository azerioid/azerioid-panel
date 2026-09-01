#!/usr/bin/env bash
# Shared logging helpers for Stack Manager installer modules.
set -euo pipefail

if [[ -t 1 ]]; then
    C_RED=$'\e[31m'; C_GRN=$'\e[32m'; C_YLW=$'\e[33m'; C_CYN=$'\e[36m'; C_RST=$'\e[0m'
else
    C_RED=""; C_GRN=""; C_YLW=""; C_CYN=""; C_RST=""
fi

info()  { echo "${C_CYN}[*]${C_RST} $*"; }
ok()    { echo "${C_GRN}[OK]${C_RST} $*"; }
warn()  { echo "${C_YLW}[!]${C_RST} $*"; }
die()   { echo "${C_RED}[ERROR]${C_RST} $*" >&2; exit 1; }
