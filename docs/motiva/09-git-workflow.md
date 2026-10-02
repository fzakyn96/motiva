# Git Workflow

- main: production-ready
- develop: integration
- feature/*: satu milestone / satu tujuan
- Workflow: feature branch dari develop -> implement scope -> test -> review diff -> commit -> push -> PR -> merge.
- Guardrail: agent dilarang reset --hard, clean -fd, force push, delete branch tanpa instruksi.