FROM wordpress

ARG IMAGEMAGICK_VERSION=6.9.11-60

RUN set -eux; \
	apt-get update; \
	apt-get install -y --no-install-recommends \
		$PHPIZE_DEPS \
		curl \
		libde265-dev \
		libheif-dev \
		libheif-plugin-aomenc \
		libheif-plugin-x265 \
		libjpeg-dev \
		libpng-dev \
		libwebp-dev \
		libxml2-dev \
		pkg-config \
	; \
	imagemagickTarball="ImageMagick-${IMAGEMAGICK_VERSION}.tar.xz"; \
	curl -fL -o "/tmp/${imagemagickTarball}" "https://imagemagick.org/archive/releases/${imagemagickTarball}"; \
	mkdir -p /usr/src/imagemagick; \
	tar -xf "/tmp/${imagemagickTarball}" -C /usr/src/imagemagick --strip-components=1; \
	cd /usr/src/imagemagick; \
	./configure --disable-static --with-heic=yes --with-jpeg=yes --with-png=yes --with-webp=yes; \
	make -j "$(nproc)"; \
	make install; \
	ldconfig; \
	pecl uninstall imagick || true; \
	pecl install -f imagick-3.8.1; \
	docker-php-ext-enable imagick; \
	rm -rf /tmp/pear /tmp/"${imagemagickTarball}" /usr/src/imagemagick; \
	rm -rf /var/lib/apt/lists/*; \
	convert --version | grep -F "ImageMagick ${IMAGEMAGICK_VERSION}"; \
	convert -list format | grep -E '^[[:space:]]*AVIF'; \
	php -r '$v = Imagick::getVersion(); echo $v["versionString"], PHP_EOL;' | grep -F "ImageMagick ${IMAGEMAGICK_VERSION}"
