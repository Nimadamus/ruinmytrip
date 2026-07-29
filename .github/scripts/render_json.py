"""Helpers for parsing Render API responses in render-deploy.yml.

Kept out of the workflow YAML because embedding multi-line Python in a
`run: |` block scalar is indentation-fragile (see git history of this file).
"""
import json
import sys


def field(path, key):
    with open(path, encoding="utf-8") as f:
        raw = f.read()
    if not raw.strip():
        sys.exit(1)
    print(json.load(open(path, encoding="utf-8"))[key])


def find_by_commit(path, sha):
    with open(path, encoding="utf-8") as f:
        raw = f.read()
    if not raw.strip():
        sys.exit(1)
    items = json.loads(raw)
    for item in items:
        deploy = item.get("deploy", item)
        commit = deploy.get("commit") or {}
        if commit.get("id") == sha:
            print(deploy["id"])
            return
    sys.exit(1)


if __name__ == "__main__":
    mode = sys.argv[1]
    try:
        if mode == "field":
            field(sys.argv[2], sys.argv[3])
        elif mode == "find_commit":
            find_by_commit(sys.argv[2], sys.argv[3])
        else:
            sys.exit(f"unknown mode: {mode}")
    except Exception:
        sys.exit(1)
