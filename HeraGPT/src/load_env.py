import os

def load_env(file_path):
    if not os.path.exists(file_path):
        raise Exception(f"The .env file does not exist at {file_path}")

    with open(file_path) as f:
        for line in f:
            if line.strip() and not line.startswith('#'):
                key, value = line.strip().split('=', 1)
                os.environ[key] = value
