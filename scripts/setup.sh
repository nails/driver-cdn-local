#!/bin/bash

mkdir -p ../../../assets/uploads
if [[ ! -f ../../../assets/uploads/.gitignore ]]; then
  echo '*' >> ../../../assets/uploads/.gitignore
  echo '!.gitignore' >> ../../../assets/uploads/.gitignore
fi
