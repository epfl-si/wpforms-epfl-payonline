SHELL := /bin/bash
PROJECT_NAME := $(shell basename $(CURDIR))
VERSION := $(shell cat wpforms-epfl-payonline.php | grep '* Version:' | awk '{print $$3}')
REPO_REMOTE := $(shell git config --get remote.origin.url)
REPO_OWNER_NAME := $(shell git config --get user.name)
REPO_OWNER_EMAIL := $(shell git config --get user.email)

all: check

check: check-wp check-zip

check-wp:
	@type wp > /dev/null 2>&1 || { echo >&2 "Please install wp-cli (https://wp-cli.org/#installing). Aborting."; exit 1; }

check-zip:
	@type zip > /dev/null 2>&1 || { echo >&2 "Please install zip. Aborting."; exit 1; }

repo:
	@echo "Remote: $(REPO_REMOTE)"
	@echo "Name: $(PROJECT_NAME)"
	@echo "Owner name: $(REPO_OWNER_NAME)"
	@echo "Owner email: $(REPO_OWNER_EMAIL)"

.PHONY: tag
tag:
	git status;
	git tag -a v$(VERSION) -m "Version $(VERSION)";
	git show v$(VERSION);
	#git push origin --tags;

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

pot: languages/$(PROJECT_NAME).pot languages/$(PROJECT_NAME)-fr_FR.po
	wp i18n make-pot . languages/$(PROJECT_NAME).pot --headers='$(JSON_HEADERS)'
	msginit --input=languages/$(PROJECT_NAME).pot --locale=fr --output=languages/$(PROJECT_NAME)-fr_FR.po
	if [ -f languages/$(PROJECT_NAME)-fr_FR.po ] ; then \
		msgmerge --update languages/$(PROJECT_NAME)-fr_FR.po languages/$(PROJECT_NAME).pot; \
	else \
		msgfmt --output-file=languages/$(PROJECT_NAME)-fr_FR.mo languages/$(PROJECT_NAME)-fr_FR.po; \
	fi;


# https://gist.github.com/mcguffin/746afffa0929ca8e2ea2ba8538776742
# API_JSON=$(printf '{"tag_name": "v%s","target_commitish": "%s","name": "v%s","body": "%s","draft": %s,"prerelease": %s}' "$VERSION" "$BRANCH" "$VERSION" "$MESSAGE" "$DRAFT" "$PRE" )
# API_RESPONSE_STATUS=$(curl --data "$API_JSON" -s -i https://api.github.com/repos/$REPO_OWNER/$REPO_NAME/releases?access_token=$GITHUB_ACCESS_TOKEN)
# echo "$API_RESPONSE_STATUS"