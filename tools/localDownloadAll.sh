#!/usr/bin/env bash

/opt/homebrew/Cellar/php/8.2.3/bin/php bin/console currency:download

/opt/homebrew/Cellar/php/8.2.3/bin/php bin/console twelve-data:download

/opt/homebrew/Cellar/php/8.2.3/bin/php bin/console pse:download
