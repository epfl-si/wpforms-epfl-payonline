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

all: check

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

.PHONY: zip
zip: check-zip
	zip -r -FS builds/wpforms-epfl-payonline-$(VERSION).zip * \
		--exclude *.git* \
		--exclude *.zip \
		--exclude \*builds\* \
		--exclude notes.md \
		--exclude Makefile

define JSON_HEADERS
{"Project-Id-Version": "WPForms EPFL Payonline $(VERSION)",\
"Last-Translator": "$(REPO_OWNER_NAME) <$(REPO_OWNER_EMAIL)>",\
"Language-Team": "EPFL IDEV-FSD <https://github.com/epfl-idevelop/$(PROJECT_NAME)>",\
"Report-Msgid-Bugs-To":"https://github.com/wp-cli/i18n-command/issues",\
"X-Domain": "$(PROJECT_NAME)"}
endef

.PHONY: pot
pot: check-wp check-gettext languages/$(PROJECT_NAME).pot
	wp i18n make-pot . languages/$(PROJECT_NAME).pot --headers='$(JSON_HEADERS)'
	if [ -f languages/$(PROJECT_NAME)-fr_FR.po ] ; then \
		msgmerge --update languages/$(PROJECT_NAME)-fr_FR.po languages/$(PROJECT_NAME).pot; \
	else \
		msginit --input=languages/$(PROJECT_NAME).pot --locale=fr --output=languages/$(PROJECT_NAME)-fr_FR.po; \
	fi
	msgfmt --output-file=languages/$(PROJECT_NAME)-fr_FR.mo languages/$(PROJECT_NAME)-fr_FR.po

.PHONY: tag
tag:
	# git status;
	git tag -a v$(VERSION)-alpha -m "Version $(VERSION)-alpha" || true;
	#git show v$(VERSION);
	git push origin --tags || true;

define RELEASE_JSON
{"tag_name": "v$(VERSION)-alpha",\
"target_commitish": "master",\
"name": "$(PROJECT_NAME) $(VERSION)-alpha",\
"body": "WIP",\
"draft": true,\
"prerelease": false}
endef
# {\"tag_name\": \"v$(VERSION)-alpha\",\
# \"target_commitish\": \"master\",\
# \"name\": \"$(PROJECT_NAME) $(VERSION)-alpha\",\
# \"body\": \"[CHANGELOG.md](https://github.com/epfl-idevelop/wpforms-epfl-payonline/blob/master/CHANGELOG.md#v$(VERSION_NO_DOTS))\",\
# \"draft\": true,\
# \"prerelease\": false}

test:
	TEST=$$(printf $(RELEASE_JSON) "[test](hola#v$(VERSION_NO_DOTS)"); \
	echo "$$TEST"

test-pre-release: tag
	# create a GitHub release, see: https://www.barrykooij.com/create-github-releases-via-command-line/
	echo "Creating a release with info: '$(RELEASE_JSON)'."
	# echo "curl --data '$(RELEASE_JSON)' -s -i '$(GH_RELEASE_URL)'"
	RELEASE_ID=$$(curl --data '$(RELEASE_JSON)' -s '$(GH_RELEASE_URL)' | jq .id); \
	echo $$RELEASE_ID

release:
	@echo "WIP"
	# https://gist.github.com/mcguffin/746afffa0929ca8e2ea2ba8538776742
	# https://gist.github.com/mjdietzx/6ec00ebd1223ca1cf87fa0c80e0bf84e
	# API_JSON=$(printf '{"tag_name": "v%s","target_commitish": "%s","name": "v%s","body": "%s","draft": %s,"prerelease": %s}' "$VERSION" "$BRANCH" "$VERSION" "$MESSAGE" "$DRAFT" "$PRE" )
	# API_RESPONSE_STATUS=$(curl --data "$API_JSON" -s -i https://api.github.com/repos/$REPO_OWNER/$REPO_NAME/releases?access_token=$GITHUB_ACCESS_TOKEN)
	# echo "$API_RESPONSE_STATUS"
