SHELL := /bin/bash


# Define some useful variables
# wpforms-epfl-payonline
PROJECT_NAME := $(shell basename $(CURDIR))
# 0.0.4
VERSION := $(shell cat wpforms-epfl-payonline.php | grep '* Version:' | awk '{print $$3}')
VERSION_NO_DOTS := $(shell echo $(VERSION) | sed -e 's/\.//g')

# git@github.com:epfl-idevelop/wpforms-epfl-payonline.git
REPO_REMOTE := $(shell git config --get remote.origin.url)
# Nicolas Borboën
REPO_OWNER_NAME := $(shell git config --get user.name)
# ponsfrilus@gmail.com
REPO_OWNER_EMAIL := $(shell git config --get user.email)
# epfl-idevelop/wpforms-epfl-payonline
REPO_GH_PATH := $(shell echo $(REPO_REMOTE) |  cut -d':' -f 2 | cut -d'.' -f 1)
# epfl-idevelop
REPO_ORG_OR_USR := $(shell echo $(REPO_GH_PATH) | cut -d'/' -f1)
# wpforms-epfl-payonline
REPO_NAME := $(shell echo $(REPO_GH_PATH) | cut -d'/' -f2)
GH_URL = https://github.com/
GH_API = https://api.github.com
# https://help.github.com/en/github/authenticating-to-github/creating-a-personal-access-token-for-the-command-line
GH_ACCESS_TOKEN := $(WPEP_GH_TOKEN)
# https://github.com/epfl-idevelop/wpforms-epfl-payonline
REPO_HTTP_URL = $(GH_URL)$(REPO_GH_PATH)
GH_RELEASE_URL = $(GH_API)/repos/$(REPO_ORG_OR_USR)/$(REPO_NAME)/releases?access_token=$(GH_ACCESS_TOKEN)

# .SILENT: all zip pot
all: check pot tag zip release 

check: check-wp check-zip check-git check-var check-jq check-curl

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

check-var:
	@echo "--------------------------------------------------------------------------------"
	@echo "Version     : $(VERSION)"
	@echo "Version     : $(VERSION_NO_DOTS)"
	@echo "Token       : $(GH_ACCESS_TOKEN)"
	@echo "Remote      : $(REPO_REMOTE)"
	@echo "Org         : $(REPO_ORG_OR_USR)"
	@echo "Repo name   : $(REPO_NAME)"
	@echo "URL         : $(REPO_HTTP_URL)"
	@echo "Proj name   : $(PROJECT_NAME)"
	@echo "Owner name  : $(REPO_OWNER_NAME)"
	@echo "Owner email : $(REPO_OWNER_EMAIL)"
	@echo "--------------------------------------------------------------------------------"
	@echo ""

.PHONY: zip
zip: check-zip
	@zip -r -FS builds/wpforms-epfl-payonline-$(VERSION).zip * \
		--exclude *.git* \
		--exclude *.zip \
		--exclude *.po~ \
		--exclude \*builds\* \
		--exclude \*doc\* \
		--exclude Makefile \
		--exclude create-gh-release.sh
	if [ -L ./builds/wpforms-epfl-payonline.zip ] ; then \
		cd ./builds; \
		ln -sfn wpforms-epfl-payonline-$(VERSION).zip ./wpforms-epfl-payonline.zip; \
		ln -sfn wpforms-epfl-payonline-$(VERSION).zip ./latest.zip; \
	else \
		cd ./builds; \
		ln -s wpforms-epfl-payonline-$(VERSION).zip ./wpforms-epfl-payonline.zip; \
		ln -s wpforms-epfl-payonline-$(VERSION).zip ./latest.zip; \
	fi


define JSON_HEADERS
{"Project-Id-Version": "WPForms EPFL Payonline $(VERSION)",\
"Last-Translator": "$(REPO_OWNER_NAME) <$(REPO_OWNER_EMAIL)>",\
"Language-Team": "EPFL IDEV-FSD <https://github.com/epfl-idevelop/$(PROJECT_NAME)>",\
"Report-Msgid-Bugs-To":"https://github.com/wp-cli/i18n-command/issues",\
"X-Domain": "$(PROJECT_NAME)"}
endef

.PHONY: pot
pot: check-wp check-gettext languages/$(PROJECT_NAME).pot
	@wp i18n make-pot . languages/$(PROJECT_NAME).pot --headers='$(JSON_HEADERS)'
	if [ -f languages/$(PROJECT_NAME)-fr_FR.po ] ; then \
		msgmerge --update languages/$(PROJECT_NAME)-fr_FR.po languages/$(PROJECT_NAME).pot; \
	else \
		msginit --input=languages/$(PROJECT_NAME).pot --locale=fr --output=languages/$(PROJECT_NAME)-fr_FR.po; \
	fi
	msgfmt --output-file=languages/$(PROJECT_NAME)-fr_FR.mo languages/$(PROJECT_NAME)-fr_FR.po

.PHONY: tag
tag:
	@git tag -a v$(VERSION) -m "Version $(VERSION)" || true;
	@git push origin --tags || true;

release: create-gh-release.sh
	./create-gh-release.sh

