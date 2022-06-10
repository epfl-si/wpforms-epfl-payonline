SHELL := /bin/bash

# Script's variables
PROJECT_NAME := $(shell basename $(CURDIR))
VERSION := $(shell cat wpforms-epfl-payonline.php | grep '* Version:' | awk '{print $$3}')
REPO_OWNER_NAME := $(shell git config --get user.name)
REPO_OWNER_EMAIL := $(shell git config --get user.email)

.PHONY: help
## Print this help (see <https://gist.github.com/klmr/575726c7e05d8780505a> for explanation)
help:
	@echo "$$(tput bold)Available rules (alphabetical order):$$(tput sgr0)";sed -ne"/^## /{h;s/.*//;:d" -e"H;n;s/^## //;td" -e"s/:.*//;G;s/\\n## /---/;s/\\n/ /g;p;}" ${MAKEFILE_LIST}|LC_ALL='C' sort -f |awk -F --- -v n=$$(tput cols) -v i=20 -v a="$$(tput setaf 6)" -v z="$$(tput sgr0)" '{printf"%s%*s%s ",a,-i,$$1,z;m=split($$2,w," ");l=n-i;for(j=1;j<=m;j++){l-=length(w[j])+1;if(l<= 0){l=n-i-length(w[j])-1;printf"\n%*s ",-i," ";}printf"%s ",w[j];}printf"\n";}'

.PHONY: check
## Check dependand softwares
check: test check-jq check-wp check-zip check-curl check-git check-gettext

## Print the scipt's variables
test:
	@echo "PROJECT_NAME:     ${PROJECT_NAME}"
	@echo "VERSION:          ${VERSION}"
	@echo "REPO_OWNER_NAME:  ${REPO_OWNER_NAME}"
	@echo "REPO_OWNER_EMAIL: ${REPO_OWNER_EMAIL}"

.PHONY: release
## 🚩 This create the whole jam for publishing a new release on GitHub, including
## a new version number, updated translation, a "Bounce version commit", a new
## tag and a new release including the wpforms-epfl-payonline.zip as asset.
release: check
	$(MAKE) version
	$(MAKE) pot zip commit tag gh-release

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
"Language-Team": "EPFL ISAS-FSD <https://github.com/epfl-si/$(PROJECT_NAME)>",\
"Report-Msgid-Bugs-To":"https://github.com/wp-cli/i18n-command/issues",\
"X-Domain": "$(PROJECT_NAME)"}
endef

.PHONY: version
## Bounce patch version (default)
version: bump-version.sh
	$(MAKE) version-patch

.PHONY: version-patch
## Bounce patch version
version-patch: bump-version.sh
	./bump-version.sh -p

.PHONY: version-minor
## Bounce minor version
version-minor: bump-version.sh
	./bump-version.sh -m

.PHONY: version-major
## Bounce major version
version-major: bump-version.sh
	./bump-version.sh -M

.PHONY: pot
## (Re)Generate the pot and mo files for translations
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
## Create the plugin's zip file and link it as ./builds/latest.zip
zip: check-zip builds/$(PROJECT_NAME)-$(VERSION).zip
	@mkdir -p builds || true
	@rm -f ./builds/$(PROJECT_NAME)-$(VERSION).zip
	cd ..; zip -r -FS $(PROJECT_NAME)/builds/$(PROJECT_NAME)-$(VERSION).zip $(PROJECT_NAME) \
		--exclude "$(PROJECT_NAME)/.*"                   \
		--exclude "$(PROJECT_NAME)/*.po.bak"             \
		--exclude "$(PROJECT_NAME)/*.*~"                 \
		--exclude "$(PROJECT_NAME)/*.orig"               \
		--exclude "$(PROJECT_NAME)/builds/*"             \
		--exclude "$(PROJECT_NAME)/doc/*"                \
		--exclude "$(PROJECT_NAME)/vendor/*"             \
		--exclude "$(PROJECT_NAME)/composer.json"        \
		--exclude "$(PROJECT_NAME)/compose.lock"         \
		--exclude "$(PROJECT_NAME)/Makefile"             \
		--exclude "$(PROJECT_NAME)/create-gh-release.sh" \
		--exclude "$(PROJECT_NAME)/bump-version.sh"; cd $(PROJECT_NAME)
	@if [ -L ./builds/$(PROJECT_NAME).zip ] ; then \
		cd ./builds; \
		ln -sfn $(PROJECT_NAME)-$(VERSION).zip ./$(PROJECT_NAME).zip; \
		ln -sfn $(PROJECT_NAME)-$(VERSION).zip ./latest.zip; \
	else \
		cd ./builds; \
		ln -s $(PROJECT_NAME)-$(VERSION).zip ./$(PROJECT_NAME).zip; \
		ln -s $(PROJECT_NAME)-$(VERSION).zip ./latest.zip; \
	fi
	@echo "Zip for version $(VERSION) is now available in ./builds/$(PROJECT_NAME).zip"

.PHONY: commit
## Interactive automated commit for new release
commit:
	@if [[ -z $$(git commit --dry-run --short | grep CHANGELOG.md) ]]; then \
		read -p "Did you forget to modify the CHANGELOG? Want to abort? [Nn]: " -n 1 -r; \
		if [[ $$REPLY =~ ^[Nn]$$ ]]; then \
			echo -e "\nContinuing....\n"; \
		else \
			echo -e "\nAborting....\n"; \
			exit 1; \
		fi \
	fi
	@git add languages/*
	@git commit -o languages -m "[T9N] Translations updated"
	@git add wpforms-epfl-payonline.php
	@git commit -o wpforms-epfl-payonline.php -m "[VER] Bump to v$(VERSION)"
	read -p "Would you like to git add and commit all? [Yy]: " -n 1 -r; \
	if [[ $$REPLY =~ ^[Yy]$$ ]]; then \
		git commit -am "[ARE] Automated releasing change" ; \
		@git push ; \
		@git status ; \
	else \
		echo -e "\nAborting....\n"; \
		exit 1; \
	fi

.PHONY: tag
## Git tag with current version
tag:
	@git tag -a v$(VERSION) -m "Version $(VERSION)"
	@git push origin --tags

.PHONY: gh-release
## Create a new GitHub release
gh-release: create-gh-release.sh
	./create-gh-release.sh

## Install PHP code sniffer, PHP CS Fixer, PHP code beautifuler and fixer and the wp-coding-standards, wpcs
install_phpcs:
	composer require squizlabs/php_codesniffer friendsofphp/php-cs-fixer wp-coding-standards/wpcs --dev -W
	./vendor/bin/phpcs --config-set installed_paths vendor/wp-coding-standards/wpcs
	./vendor/bin/phpcs --config-set default_standard WordPress-Core

.PHONY: phpcs
## Run PHP Code Sniffer linter using WordPress coding standards
phpcs:
	@echo '**** run phpcs ****'
	./vendor/bin/phpcs --standard=WordPress --extensions=php --ignore="vendor/*,lib" .

.PHONY: phpcs-wpcore
## Run PHP Code Sniffer linter using WordPress-Core coding standards
phpcs-wpcore:
	@echo '**** run phpcs-wpcore ****'
	./vendor/bin/phpcs --standard=WordPress-Core --extensions=php --ignore="vendor/*,lib" .

.PHONY: phpcbf
## Run PHP Code Beautifuller and Fixer (it fixes what it can)
phpcbf:
	@echo '**** run phpcbf ****'
	./vendor/bin/phpcbf -pv --standard=WordPress --extensions=php --ignore="vendor/*,lib" .
	# ./vendor/bin/phpcbf -pv --standard=WordPress-Core --extensions=php --ignore="vendor/*,lib" .

.PHONY: phpcbf-wpcore
## Run PHP Code Beautifuller and Fixer (it fixes what it can)
phpcbf-wpcore:
	@echo '**** run phpcbf-wpcore ****'
	./vendor/bin/phpcbf -pv --standard=WordPress-Core --extensions=php --ignore="vendor/*,lib" .
