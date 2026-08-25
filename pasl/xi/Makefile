# xi — XIP book server
PHP      ?= php
HOST     ?= 127.0.0.1
PORT     ?= 8765
CONFIG   ?= config.json
XI       := $(PHP) xi.php

.PHONY: help start stop status foreground test drop docker-build docker-run clean

help:
	@echo "xi XIP book server"
	@echo "  make start          start daemon on $(HOST):$(PORT)"
	@echo "  make stop           stop daemon"
	@echo "  make status         status"
	@echo "  make foreground     run in foreground"
	@echo "  make test           one-shot engine smoke test"
	@echo "  make drop           sample JSON drop into cover inbox"
	@echo "  make docker-build   build Docker image"
	@echo "  make docker-run     run container :8765"
	@echo "  make docs           build Books PDF (needs reportlab)"

start:
	$(XI) $(HOST):$(PORT) start $(CONFIG)

stop:
	$(XI) $(HOST):$(PORT) stop

status:
	$(XI) $(HOST):$(PORT) status

foreground:
	$(XI) $(HOST):$(PORT) start $(CONFIG) --foreground

test:
	$(PHP) scripts/smoke.php

drop:
	mkdir -p data/books/cover/inbox
	echo '{"message":"hello from drop","ts":"'"$$(date -Iseconds)"'"}' > data/books/cover/inbox/sample.json
	@echo "dropped data/books/cover/inbox/sample.json — open home leaf (next interaction)"

docker-build:
	docker build -t pasl-xi -f Dockerfile .

docker-run:
	docker run --rm -p $(PORT):8765 -v "$$(pwd)/data:/app/data" pasl-xi

docs:
	$(PHP) -r 'echo "see docs/ or: python3 docs/build_pdf.py\n";'
	python3 docs/build_pdf.py

clean:
	rm -rf data/books/*/channels/*.json data/books/*/binding.json data/books/*/inbox/processed/*
	-$(XI) $(HOST):$(PORT) stop 2>/dev/null || true
