# wp-env ImageMagick Overrides

This repo patches the local `@wordpress/env` package with `patch-package` so `wp-env` can use a custom base image.

## Why This Exists

This setup is for reproducing the ImageMagick 6 AVIF transparency bug that affects transparent PNG to AVIF conversion.

Relevant upstream references:

- The often-cited commit `cc4e5a6` is not the fix.
- `cc4e5a6383961c03d340e0237feedfff83f9af0b` is an XMP-profile change in `coders/heic.c`, not the alpha fix:
  - https://github.com/ImageMagick/ImageMagick6/commit/cc4e5a6383961c03d340e0237feedfff83f9af0b
- AVIF support first enters the ImageMagick 6 branch at:
  - `e353f91e6c43e29669d1d4e445c6452c16463130`
  - https://github.com/ImageMagick/ImageMagick6/commit/e353f91e6c43e29669d1d4e445c6452c16463130
- The actual transparency fix is:
  - `0a44a52da4ccfd077a91a138b82a24276d3aa3ef`
  - https://github.com/ImageMagick/ImageMagick6/commit/0a44a52da4ccfd077a91a138b82a24276d3aa3ef
- Practical broken IM6 range:
  - `6.9.11-20` through `6.9.12-67`
- First fixed IM6 release:
  - `6.9.12-68`

## Root Cause

In the broken ImageMagick 6 AVIF writer, transparent images still go through the old YCbCr path:

- `heif_colorspace_YCbCr`
- `heif_chroma_420`

That path writes Y, Cb, and Cr planes, but does not preserve alpha correctly for transparent PNG input.

The fix adds a matte-aware RGBA path for AVIF output, using libheif modes such as:

- `heif_colorspace_RGB`
- `heif_chroma_interleaved_RGBA`
- `heif_chroma_interleaved_RRGGBBAA_LE`

That is the meaningful change: matte images stop using the old YCbCr 4:2:0 write path and start writing RGBA-capable output.

## Why Search Results Are Misleading

- Alex Chan's write-up correctly points to fixed release `6.9.12-68`, but the linked commit hash is wrong:
  - https://alexwlchan.net/2023/check-for-transparency/
- WordPress Performance issue `#2237` correctly identifies `6.9.11-60` as broken, but it is a downstream report, not the upstream fix record:
  - https://github.com/WordPress/performance/issues/2237
- The fix landed as part of broader `heic.c` work, which is why commit cross-links and quick summaries often point at the wrong thing.

## Supported config

`dockerImages` accepts these service keys:

- `wordpress`
- `cli`

Example:

```json
{
	"dockerImages": {
		"wordpress": "performance-imagick:wp-env-wordpress-im6-broken"
	}
}
```

When set, `wp-env` will generate `FROM <that-image>` for the corresponding service instead of the default `wordpress` or `wordpress:cli` image.

## Build the custom images

Broken ImageMagick 6.9.11-60:

```bash
npm run wp-env:build-im6-broken
```

Fixed ImageMagick 6.9.12-68:

```bash
npm run wp-env:build-im6-fixed
```

## Start wp-env with the broken or fixed image

Development environment:

```bash
npm run wp-env:start:im6-broken
npm run wp-env:start:im6-fixed
```

Tests environment:

```bash
npm run wp-env:test:start:im6-broken
npm run wp-env:test:start:im6-fixed
```

## Notes

- These committed configs only override the `wordpress` service, because the AVIF transparency bug is reproduced through the web/media upload path.
