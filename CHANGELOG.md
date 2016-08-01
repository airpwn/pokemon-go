# Changelog

All Notable changes to `pokemon-go` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](http://keepachangelog.com/) principles.

## 2016-08-01

### Added
- Now retries the request if "unknown" is returned
- Max retry limit

## [v1.0.0-alpha1] - 2016-07-31

### Added
- Save Endpoint with AuthTicket
- Randomized RpcId
- Returns initial response (PlayerData)

### Changed
- Location is now optional
- Supports multiple requests/responses in a single envelope

### Fixed
- Response-Code-Check: No longer checking code of the request

## 2016-07-27 #2

### Changed
- Updated Protobufs dependency (fixes Enum namespace problem)

## 2016-07-27 #1

### Added
- Try to obtain a new AuthTicket if an AuthError occurs using a cached one
- Check for handshake if an initial request is done
- Check for unknown response status code
- Api Requests as own classes being passed to new Client->sendRequest
- GetPlayerRequest

### Changed
- Updated Protobufs dependency
- Moved Request classes into own namespace
- Renamed Client->sendRequest to sendRequestRaw

### Fixed
- Only resend if the received Api-URL has changed

## 2016-07-26 #1

### Added
- Caching of AuthTicket

### Fixed
- Sending of request: Resends if AuthTicket/Api-URL has been received OR server sends HandShake Code

### Removed
- Multiple RequestTypes in initial request
