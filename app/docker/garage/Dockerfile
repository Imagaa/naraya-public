FROM dxflrs/garage:v1.0.1

# garage.toml no longer contains a secret (GARAGE_RPC_SECRET is injected as
# an env var at runtime — Garage supports this natively), so baking the
# config file itself into the image is safe.
COPY garage.toml /etc/garage.toml
