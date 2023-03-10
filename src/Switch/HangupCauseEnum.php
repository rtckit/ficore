<?php

declare(strict_types=1);

namespace RTCKit\FiCore\Switch;

enum HangupCauseEnum: string
{
    case NONE = 'NONE';
    case UNALLOCATED_NUMBER = 'UNALLOCATED_NUMBER';
    case NO_ROUTE_TRANSIT_NET = 'NO_ROUTE_TRANSIT_NET';
    case NO_ROUTE_DESTINATION = 'NO_ROUTE_DESTINATION';
    case CHANNEL_UNACCEPTABLE = 'CHANNEL_UNACCEPTABLE';
    case CALL_AWARDED_DELIVERED = 'CALL_AWARDED_DELIVERED';
    case NORMAL_CLEARING = 'NORMAL_CLEARING';
    case USER_BUSY = 'USER_BUSY';
    case NO_USER_RESPONSE = 'NO_USER_RESPONSE';
    case NO_ANSWER = 'NO_ANSWER';
    case SUBSCRIBER_ABSENT = 'SUBSCRIBER_ABSENT';
    case CALL_REJECTED = 'CALL_REJECTED';
    case NUMBER_CHANGED = 'NUMBER_CHANGED';
    case REDIRECTION_TO_NEW_DESTINATION = 'REDIRECTION_TO_NEW_DESTINATION';
    case EXCHANGE_ROUTING_ERROR = 'EXCHANGE_ROUTING_ERROR';
    case DESTINATION_OUT_OF_ORDER = 'DESTINATION_OUT_OF_ORDER';
    case INVALID_NUMBER_FORMAT = 'INVALID_NUMBER_FORMAT';
    case FACILITY_REJECTED = 'FACILITY_REJECTED';
    case RESPONSE_TO_STATUS_ENQUIRY = 'RESPONSE_TO_STATUS_ENQUIRY';
    case NORMAL_UNSPECIFIED = 'NORMAL_UNSPECIFIED';
    case NORMAL_CIRCUIT_CONGESTION = 'NORMAL_CIRCUIT_CONGESTION';
    case NETWORK_OUT_OF_ORDER = 'NETWORK_OUT_OF_ORDER';
    case NORMAL_TEMPORARY_FAILURE = 'NORMAL_TEMPORARY_FAILURE';
    case SWITCH_CONGESTION = 'SWITCH_CONGESTION';
    case ACCESS_INFO_DISCARDED = 'ACCESS_INFO_DISCARDED';
    case REQUESTED_CHAN_UNAVAIL = 'REQUESTED_CHAN_UNAVAIL';
    case PRE_EMPTED = 'PRE_EMPTED';
    case FACILITY_NOT_SUBSCRIBED = 'FACILITY_NOT_SUBSCRIBED';
    case OUTGOING_CALL_BARRED = 'OUTGOING_CALL_BARRED';
    case INCOMING_CALL_BARRED = 'INCOMING_CALL_BARRED';
    case BEARERCAPABILITY_NOTAUTH = 'BEARERCAPABILITY_NOTAUTH';
    case BEARERCAPABILITY_NOTAVAIL = 'BEARERCAPABILITY_NOTAVAIL';
    case SERVICE_UNAVAILABLE = 'SERVICE_UNAVAILABLE';
    case BEARERCAPABILITY_NOTIMPL = 'BEARERCAPABILITY_NOTIMPL';
    case CHAN_NOT_IMPLEMENTED = 'CHAN_NOT_IMPLEMENTED';
    case FACILITY_NOT_IMPLEMENTED = 'FACILITY_NOT_IMPLEMENTED';
    case SERVICE_NOT_IMPLEMENTED = 'SERVICE_NOT_IMPLEMENTED';
    case INVALID_CALL_REFERENCE = 'INVALID_CALL_REFERENCE';
    case INCOMPATIBLE_DESTINATION = 'INCOMPATIBLE_DESTINATION';
    case INVALID_MSG_UNSPECIFIED = 'INVALID_MSG_UNSPECIFIED';
    case MANDATORY_IE_MISSING = 'MANDATORY_IE_MISSING';
    case MESSAGE_TYPE_NONEXIST = 'MESSAGE_TYPE_NONEXIST';
    case WRONG_MESSAGE = 'WRONG_MESSAGE';
    case IE_NONEXIST = 'IE_NONEXIST';
    case INVALID_IE_CONTENTS = 'INVALID_IE_CONTENTS';
    case WRONG_CALL_STATE = 'WRONG_CALL_STATE';
    case RECOVERY_ON_TIMER_EXPIRE = 'RECOVERY_ON_TIMER_EXPIRE';
    case MANDATORY_IE_LENGTH_ERROR = 'MANDATORY_IE_LENGTH_ERROR';
    case PROTOCOL_ERROR = 'PROTOCOL_ERROR';
    case INTERWORKING = 'INTERWORKING';
    case SUCCESS = 'SUCCESS';
    case ORIGINATOR_CANCEL = 'ORIGINATOR_CANCEL';
    case CRASH = 'CRASH';
    case SYSTEM_SHUTDOWN = 'SYSTEM_SHUTDOWN';
    case LOSE_RACE = 'LOSE_RACE';
    case MANAGER_REQUEST = 'MANAGER_REQUEST';
    case BLIND_TRANSFER = 'BLIND_TRANSFER';
    case ATTENDED_TRANSFER = 'ATTENDED_TRANSFER';
    case ALLOTTED_TIMEOUT = 'ALLOTTED_TIMEOUT';
    case USER_CHALLENGE = 'USER_CHALLENGE';
    case MEDIA_TIMEOUT = 'MEDIA_TIMEOUT';
    case PICKED_OFF = 'PICKED_OFF';
    case USER_NOT_REGISTERED = 'USER_NOT_REGISTERED';
    case PROGRESS_TIMEOUT = 'PROGRESS_TIMEOUT';
    case INVALID_GATEWAY = 'INVALID_GATEWAY';
    case GATEWAY_DOWN = 'GATEWAY_DOWN';
    case INVALID_URL = 'INVALID_URL';
    case INVALID_PROFILE = 'INVALID_PROFILE';
    case NO_PICKUP = 'NO_PICKUP';
    case SRTP_READ_ERROR = 'SRTP_READ_ERROR';
    case BOWOUT = 'BOWOUT';
    case BUSY_EVERYWHERE = 'BUSY_EVERYWHERE';
    case DECLINE = 'DECLINE';
    case DOES_NOT_EXIST_ANYWHERE = 'DOES_NOT_EXIST_ANYWHERE';
    case NOT_ACCEPTABLE = 'NOT_ACCEPTABLE';
    case UNWANTED = 'UNWANTED';
    case NO_IDENTITY = 'NO_IDENTITY';
    case BAD_IDENTITY_INFO = 'BAD_IDENTITY_INFO';
    case UNSUPPORTED_CERTIFICATE = 'UNSUPPORTED_CERTIFICATE';
    case INVALID_IDENTITY = 'INVALID_IDENTITY';
    case STALE_DATE = 'STALE_DATE';
}
