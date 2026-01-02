FROM node:22-slim

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    procps \
    && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /workspace

# Install global tools
RUN npm install -g @nestjs/cli prisma

# Default command
CMD ["sleep", "infinity"]

