FROM public.ecr.aws/sam/build-python3.12:latest

RUN pip install --no-cache-dir awscli

WORKDIR /workspace

COPY docker/installer/entrypoint.sh /usr/local/bin/installer-entrypoint.sh
COPY docker/installer/scripts/ /usr/local/lib/teemops-installer/

RUN chmod +x /usr/local/bin/installer-entrypoint.sh \
    && chmod +x /usr/local/lib/teemops-installer/*.sh

ENTRYPOINT ["/usr/local/bin/installer-entrypoint.sh"]
