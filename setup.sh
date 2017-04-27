#!/usr/bin/env bash

command -v git >/dev/null 2>&1 || {
    echo >&2 "Git is not installed";
    exit 1;
}

echo "Updating submodules";

if git submodule update >/dev/null ; then
    echo "submodules updated";
fi