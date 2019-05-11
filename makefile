minify-js = closure-compiler --jscomp_off misplacedTypeAnnotation
gen-doc = asciidoctor

DOC_PATH = doc/fr_FR

all: desktop/js/jMQTT.min.js

%.min.js: %.js
	$(minify-js) --js $< --js_output_file $@

doc:
	cd docs; bundle exec jekyll serve

chmod:
	find . -type f -exec chmod 664 {} \;
	chmod 774 resources/install_apt.sh
