import os

def load_env(env_path):
    with open(env_path) as f:
        for line in f:
            if line.startswith('#') or '=' not in line:
                continue
            key, value = line.strip().split('=', 1)
            os.environ[key] = value
