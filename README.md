# Cloudinary Export CSV List

Export and download list of all stored resources on Cloudinary as CSV file.

![cloudinary export csv list
 screenshot](https://raw.githubusercontent.com/atakanau/cloudinary-export-csv-list/master/screenshot.png)
 
| Key | Value |
| ------------- | ------------- |
| asset_id | 6b473f8eeebda3106ef15db6ad9e3b86 |
| public_id | sample |
| format | jpg |
| version | 1545470987 |
| resource_type | image |
| type | upload |
| created_at | 2018-12-22T09:29:47Z |
| bytes | 109669 |
| width | 864 |
| height | 576 |
| folder | my_images |
| access_mode |  |
| url | http://res.cloudinary.com/[CLOUD_NAME]/image/upload/[V]/sample.jpg |
| secure_url | https://res.cloudinary.com/[CLOUD_NAME]/image/upload/[V]/sample.jpg |

Blog: [Cloudinary Export All Assets Csv List](https://atakanau.blogspot.com/2018/12/cloudinary-yuklu-tum-dosyalar-listeleme.html)

### Changelog

#### Version 2.0.0 (January 2026)
- Improved pagination and data fetching: Enhanced handling of large accounts with more efficient API calls and better rate limit resilience.
- Better CSV column consistency: Automatically normalizes columns across resource types (image, video, raw) for a cleaner, more uniform output.
- Updated authentication and API endpoint usage: Switched to recommended practices for secure API access.
- Added error handling and logging: Basic checks for API errors and invalid responses to prevent silent failures.
- Code refactoring and cleanup: Modernized PHP code structure, removed deprecated functions, and added comments for easier maintenance.
- Filename update: Default export filename now reflects the current date or version for better organization.

#### Version 1.0.0 (December 2018)
- Initial release (legacy).
