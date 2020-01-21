SHELL := /bin/bash

# Define some useful variables
# wpforms-epfl-payonline
PROJECT_NAME := $(shell basename $(CURDIR))
# 0.0.4
VERSION := $(shell cat wpforms-epfl-payonline.php | grep '* Version:' | awk '{print $$3}')
# Nicolas Borboën
REPO_OWNER_NAME := $(shell git config --get user.name)
# ponsfrilus@gmail.com
REPO_OWNER_EMAIL := $(shell git config --get user.email)

# This create the whole jam for publishing a new release on github, including 
# a new version number, updated translation, a "Bounce version commit", a new
# tag and a new release including the wpforms-epfl-payonline.zip as asset.
.PHONY: release
release: check
	$(MAKE) version
	$(MAKE) pot zip commit tag gh-release

.PHONY: check
check: check-wp check-zip check-git check-jq check-curl

check-jq:
	@type jq > /dev/null 2>&1 || { echo >&2 "Please install jq. Aborting."; exit 1; }

check-wp:
	@type wp > /dev/null 2>&1 || { echo >&2 "Please install wp-cli (https://wp-cli.org/#installing). Aborting."; exit 1; }

check-zip:
	@type zip > /dev/null 2>&1 || { echo >&2 "Please install zip. Aborting."; exit 1; }

check-curl:
	@type curl > /dev/null 2>&1 || { echo >&2 "Please install curl. Aborting."; exit 1; }

check-git:
	@type git > /dev/null 2>&1 || { echo >&2 "Please install git. Aborting."; exit 1; }

check-gettext:
	@type gettext > /dev/null 2>&1 || { echo >&2 "Please install gettext. Aborting."; exit 1; }

define JSON_HEADERS
{"Project-Id-Version": "WPForms EPFL Payonline $(VERSION)",\
"Last-Translator": "$(REPO_OWNER_NAME) <$(REPO_OWNER_EMAIL)>",\
"Language-Team": "EPFL IDEV-FSD <https://github.com/epfl-idevelop/$(PROJECT_NAME)>",\
"Report-Msgid-Bugs-To":"https://github.com/wp-cli/i18n-command/issues",\
"X-Domain": "$(PROJECT_NAME)"}
endef

# By default, bounce patch version
.PHONY: version
version: bump-version.sh
	$(MAKE) version-patch

.PHONY: version-patch
version-patch: bump-version.sh
	./bump-version.sh -p

.PHONY: version-minor
version-minor: bump-version.sh
	./bump-version.sh -m

.PHONY: version-major
version-major: bump-version.sh
	./bump-version.sh -M

.PHONY: pot
pot: check-wp check-gettext languages/$(PROJECT_NAME).pot
	@wp i18n make-pot . languages/$(PROJECT_NAME).pot --headers='$(JSON_HEADERS)'
	if [ -f languages/$(PROJECT_NAME)-fr_FR.po ] ; then \
		sed -i.bak '/Project-Id-Version:/c "Project-Id-Version: WPForms EPFL Payonline $(VERSION)\\n"' languages/$(PROJECT_NAME)-fr_FR.po; \
		msgmerge --update languages/$(PROJECT_NAME)-fr_FR.po languages/$(PROJECT_NAME).pot; \
	else \
		msginit --input=languages/$(PROJECT_NAME).pot --locale=fr --output=languages/$(PROJECT_NAME)-fr_FR.po; \
	fi
	msgfmt --output-file=languages/$(PROJECT_NAME)-fr_FR.mo languages/$(PROJECT_NAME)-fr_FR.po

.PHONY: zip
zip: check-zip
	@mkdir builds
	@zip -r -FS builds/wpforms-epfl-payonline-$(VERSION).zip * \
		--exclude *.git* \
		--exclude *.zip \
		--exclude *.po~ \
		--exclude *.php.bak \
		--exclude *.po.bak \
		--exclude \*builds\* \
		--exclude \*doc\* \
		--exclude Makefile \
		--exclude create-gh-release.sh \
		--exclude bump-version.sh
	@if [ -L ./builds/wpforms-epfl-payonline.zip ] ; then \
		cd ./builds; \
		ln -sfn wpforms-epfl-payonline-$(VERSION).zip ./wpforms-epfl-payonline.zip; \
		ln -sfn wpforms-epfl-payonline-$(VERSION).zip ./latest.zip; \
	else \
		cd ./builds; \
		ln -s wpforms-epfl-payonline-$(VERSION).zip ./wpforms-epfl-payonline.zip; \
		ln -s wpforms-epfl-payonline-$(VERSION).zip ./latest.zip; \
	fi
	@echo "Zip for version $(VERSION) is now available in ./builds/wpforms-epfl-payonline.zip"

.PHONY: commit
commit:
	@if [[ -z $$(git commit --dry-run --short | grep CHANGELOG.md) ]]; then \
		read -p "Did you forget to modify the CHANGELOG? Want to abort? [Yy]: " -n 1 -r; \
		if [[ $$REPLY =~ ^[Yy]$$ ]]; then \
			echo -e "\nAborting....\n"; \
			exit 1; \
		else \
			echo -e "\nContinuing....\n"; \
		fi \
	fi
	@-git add languages/*
	@-git commit -o languages -m "[T9N] Translations updated"
	@-git add wpforms-epfl-payonline.php
	@-git commit -o wpforms-epfl-payonline.php -m "[VER] Bump to v$(VERSION)"
	read -p "Would you like to git add and commit all? [Yy]: " -n 1 -r; \
	if [[ $$REPLY =~ ^[Yy]$$ ]]; then \
		git commit -am "[ARE] Automated releasing change" ; \
	fi
	@-git push
	@-git status

.PHONY: tag
tag:
	@-git tag -a v$(VERSION) -m "Version $(VERSION)"
	@-git push origin --tags

.PHONY: gh-release
gh-release: create-gh-release.sh
	./create-gh-release.sh

