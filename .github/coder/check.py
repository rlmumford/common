#!/usr/bin/env python3
"""Run Drupal Coder on existing PHP files changed by the pull request."""
import os
import pathlib
import subprocess
import sys

base = os.environ["CODER_BASE_SHA"]
head = os.environ["CODER_HEAD_SHA"]
changed = subprocess.check_output([
    "git", "diff", "--name-only", "--diff-filter=ACMR", "-z",
    f"{base}...{head}",
]).decode().split("\0")
extensions = {".php", ".module", ".inc", ".install", ".test", ".profile", ".theme"}
files = [f for f in changed if pathlib.Path(f).suffix in extensions
         and pathlib.Path(f).is_file()]
if not files:
    print("No changed PHP files to check.")
    sys.exit(0)
print(f"Checking {len(files)} changed PHP files.", flush=True)
fix = "--fix" in sys.argv[1:]
command = "phpcbf" if fix else "phpcs"
result = subprocess.call([
    f".github/coder/vendor/bin/{command}", "--standard=Drupal,DrupalPractice",
    "--extensions=php,module,inc,install,test,profile,theme", "-s", "--",
    *files,
])
if not fix:
    sys.exit(result)
# PHPCBF uses nonzero statuses for corrected or remaining violations. PHPCS
# runs afterwards and remains authoritative for anything not automatically fixed.
if result not in (0, 1, 2, 3):
    sys.exit(result)
changed = subprocess.call(["git", "diff", "--quiet", "--", *files])
if changed == 0:
    sys.exit(0)
if changed != 1:
    sys.exit(changed)
subprocess.check_call(["git", "config", "user.name", "github-actions[bot]"])
subprocess.check_call(["git", "config", "user.email", "41898282+github-actions[bot]@users.noreply.github.com"])
subprocess.check_call(["git", "add", "--", *files])
subprocess.check_call(["git", "commit", "-m", "style: apply Drupal Coder fixes"])
# A normal push fails safely if the PR branch advanced while this job ran.
subprocess.check_call(["git", "push", "origin", f"HEAD:refs/heads/{os.environ['CODER_HEAD_REF']}"])
with open(os.environ["GITHUB_OUTPUT"], "a") as output:
    output.write("changed=true\n")
