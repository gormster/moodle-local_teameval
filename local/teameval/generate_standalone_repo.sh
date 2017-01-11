#!/bin/bash

if [[ $# < 1 ]]
    then echo "Format: $0 target_repo_path"
    exit 1
fi

cd $(git rev-parse --show-toplevel)

# generate mod_assign patch

git diff MOODLE_31_STABLE MOODLE_31_STABLE_teameval -- mod/assign > $1/mod_assign.patch

# local

if [ ! -d $1/local ]; then
    mkdir $1/local
fi

# local/teameval

if [ -d $1/local/teameval ]; then
    rm -rf $1/local/teameval
fi

cp -R local/teameval $1/local/teameval

# local/searchable

if [ -d $1/local/searchable ]; then
    rm -rf $1/local/searchable
fi

cp -R local/searchable $1/local/searchable

# blocks

if [ ! -d $1/blocks ]; then
    mkdir $1/blocks
fi

# blocks/teameval_templates

if [ -d $1/blocks/teameval_templates ]; then
    rm -rf $1/blocks/teameval_templates
fi

cp -Rf blocks/teameval_templates $1/blocks/teameval_templates

# cleanup actions

mv $1/local/teameval/IMPLEMENTERS.md $1/