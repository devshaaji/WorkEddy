# WorkEddy Project Structure Alignment

This repository now includes the recommended scaffold from `requirements.md` section **Project Structure** while preserving the active runtime paths under `/api`, `/workers`, `/frontend`, and `/infra`.

- **Active runtime today:** `/api`, `/workers/src`, `/infra/nginx`, `docker-compose.yml`
- **Recommended scaffold added:** `/app`, `/routes`, `/views`, `/ml`, `/infrastructure`, `/scripts`, `/tests`

These scaffold files are placeholders to support iterative migration without breaking current running services.
