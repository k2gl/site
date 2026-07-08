# Minimal GoAccess image for k2gl.com traffic analytics. Built, pushed and
# provenance-attested alongside the site image — the family's own supply-chain
# story applied to its own tooling. The report-refresh loop lives in the
# goaccess service command in deploy/docker-compose.yml, so this stays a plain
# goaccess + busybox base (busybox already provides sh, cat and zcat).
FROM alpine:3.21
RUN apk add --no-cache goaccess tzdata
ENTRYPOINT ["goaccess"]
