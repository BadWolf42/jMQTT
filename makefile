minify-js = closure-compiler --jscomp_off misplacedTypeAnnotation
gen-doc = asciidoctor

DOC_PATH = doc/fr_FR

all: desktop/js/jMQTT.min.js doc

%.min.js: %.js
	$(minify-js) --js $< --js_output_file $@

doc: $(DOC_PATH)/index.html doc/*/index.html

$(DOC_PATH)/index.html: $(DOC_PATH)/*.asciidoc
	$(gen-doc) -n -a toclevels=4 -a toc=left -a icons=font@ $(DOC_PATH)/index.asciidoc -o $@
	cp $@ doc/de_DE/.
	cp $@ doc/en_US/.
	cp $@ doc/es_ES/.
	cp $@ doc/id_ID/.
	cp $@ doc/it_IT/.
	cp $@ doc/ru_RU/.

chmod:
	find . -type f -exec chmod 664 {} \;
	chmod 774 resources/install_apt.sh
