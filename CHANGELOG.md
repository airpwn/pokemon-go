# Changelog

All Notable changes to `pokemon-go` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](http://keepachangelog.com/) principles.

## 2016-07-26 #1

### Added
- Caching of AuthTicket

### Fixed
- Sending of request: Resends if AuthTicket/Api-URL has been received OR server sends HandShake Code

### Removed
- Multiple RequestTypes in initial request
