# Common-password list provenance

- Source: [SecLists `10k-most-common.txt`](https://github.com/danielmiessler/SecLists/blob/913b327317496d062bcc7cace524aaad8a693be2/Passwords/Common-Credentials/10k-most-common.txt)
- Upstream commit: `913b327317496d062bcc7cace524aaad8a693be2` (2026-09-08 UTC)
- Retrieval date: 2026-10-28
- Licence: MIT; the upstream repository's `LICENSE` at the pinned commit grants redistribution with its copyright and permission notice.
- Upstream file SHA-256: `68782d6a4a19a4768d5f15dd66bd534e7a33055cc755411e33f16d18c50fdcce`
- Embedded file SHA-256: `571396174c8cb7df541244e4c89d841528eb8b5790fc46c0f2b52717059becfb`
- Embedded entries: exactly 10,000 lowercase, unique lines. The pinned upstream file contains 10,001 entries; this file retains the first 9,999 in upstream order and appends `password1234`, which the task requires the policy to reject but which is absent from the upstream file.

The upstream licence notice is reproduced here:

```text
MIT License

Copyright (c) 2018 Daniel Miessler

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```
