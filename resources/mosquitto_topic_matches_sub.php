<?php

/*
 * Copyright (c) 2009-2020 Roger Light <roger@atchoo.org>
 * All rights reserved. This program and the accompanying materials
 * are made available under the terms of the Eclipse Public License 2.0
 * and Eclipse Distribution License v1.0 which accompany this distribution.
 * The Eclipse Public License is available at
 *    https://www.eclipse.org/legal/epl-2.0/
 * and the Eclipse Distribution License is available at
 *    http://www.eclipse.org/org/documents/edl-v10.php.
 * SPDX-License-Identifier: EPL-2.0 OR BSD-3-Clause
 * Contributors:
 *    Roger Light - initial implementation and documentation.
 *    Domochip - transcoding to PHP
 */

// This file can be found here :
// https://gist.github.com/Domochip/409bbb54c9505e0c98b16cf0b51179d0


// This function is a transcoding of Mosquitto utils function mosquitto_topic_matches_sub
// https://github.com/eclipse/mosquitto/blob/34522913ea0666c4ecd765e3327f435f60572e97/lib/util_topic.c#L192


/**
 * Does a topic match a subscription?
 *
 * @param string $sub
 * @param string $topic
 * @return bool
 */
function mosquitto_topic_matches_sub($sub, $topic) {

    $result = false;

    if (
        gettype($sub) != 'string'
        || gettype($topic) != 'string'
    ) {
        return false; // MOSQ_ERR_INVAL
    }
    if (strlen($sub) == 0 || strlen($topic) == 0) {
        return false; // MOSQ_ERR_INVAL
    }

    if (
        ($sub[0] == '$' && $topic[0] != '$')
        || ($topic[0] == '$' && $sub[0] != '$')
    ) {
        return false; // MOSQ_ERR_SUCCESS
    }

    $spos = 0;
    $previoussubchar = '';

    while (strlen($sub) > 0) {
        if (strlen($topic) > 0 && ($topic[0] == '+' || $topic[0] == '#')) {  // PHP transcoding : strlen added
            return false; // MOSQ_ERR_INVAL
        }
        if (strlen($topic) == 0 || $sub[0] != $topic[0]) { /* Check for wildcard matches */   // PHP transcoding : order of condition changed
            if ($sub[0] == '+') {
                /* Check for bad "+foo" or "a/+foo" subscription */
                if (
                    $spos > 0
                    && $previoussubchar != '/'
                ) {
                    return false; // MOSQ_ERR_INVAL
                }
                /* Check for bad "foo+" or "foo+/a" subscription */
                if (strlen($sub) > 1 && $sub[1] != '/') {
                    return false; // MOSQ_ERR_INVAL
                }
                $spos++;
                $previoussubchar = $sub[0];
                $sub = substr($sub, 1);
                while (strlen($topic) > 0 && $topic[0] != '/') {
                    if ($topic[0] == '+' || $topic[0] == '#') {
                        return false; // MOSQ_ERR_INVAL
                    }
                    $topic = substr($topic, 1);
                }
                if (strlen($topic) == 0 && strlen($sub) == 0) {
                    $result = true;
                    return $result; // MOSQ_ERR_SUCCESS
                }
            } else if ($sub[0] == '#') {
                /* Check for bad "foo#" subscription */
                if (
                    $spos > 0
                    && $previoussubchar != '/'
                ) {
                    return false; // MOSQ_ERR_INVAL
                }
                /* Check for # not the final character of the sub, e.g. "#foo" */
                if (strlen($sub) > 1) {
                    return false; // MOSQ_ERR_INVAL
                } else {
                    while (strlen($topic) > 0) {
                        if ($topic[0] == '+' || $topic[0] == '#') {
                            return false; // MOSQ_ERR_INVAL
                        }
                        $topic = substr($topic, 1);
                    }
                    $result = true;
                    return $result; // MOSQ_ERR_SUCCESS
                }
            } else {
                /* Check for e.g. foo/bar matching foo/+/# */
                if (
                    strlen($topic) == 0
                    && $spos > 0
                    && $previoussubchar == '+'
                    && $sub[0] == '/'
                    && $sub[1] == '#'
                ) {
                    $result = true;
                    return $result; // MOSQ_ERR_SUCCESS
                }

                /* There is no match at this point, but is the sub invalid? */
                while (strlen($sub) > 0) {
                    if ($sub[0] == '#' && strlen($sub) > 1) {
                        return false; // MOSQ_ERR_INVAL
                    }
                    $spos++;
                    $previoussubchar = $sub[0];
                    $sub = substr($sub, 1);
                }

                /* Valid input, but no match */
                return $result; // MOSQ_ERR_SUCCESS
            }
        } else {
            /* sub[spos] == topic[tpos] */
            if (strlen($topic) == 1) {
                /* Check for e.g. foo matching foo/# */
                if (
                    strlen($sub) == 3 // PHP transcoding : order of condition changed
                    && $sub[1] == '/'
                    && $sub[2] == '#'
                ) {
                    $result = true;
                    return $result; // MOSQ_ERR_SUCCESS
                }
            }
            $spos++;
            $previoussubchar = $sub[0];
            $sub=substr($sub, 1);
            $topic=substr($topic, 1);
            if (strlen($sub) == 0 && strlen($topic) == 0) {
                $result = true;
                return $result; // MOSQ_ERR_SUCCESS
            } else if (
                strlen($topic) == 0
                && $sub[0] == '+'
                && strlen($sub) == 1
            ) {
                if (
                    $spos > 0
                    && $previoussubchar != '/'
                ) {
                    return false; // MOSQ_ERR_INVAL
                }
                $spos++;
                $previoussubchar = $sub[0];
                $sub = substr($sub, 1);
                $result = true;
                return $result; // MOSQ_ERR_SUCCESS
            }
        }
    }
    if (
        strlen($topic) > 0
        || strlen($sub) > 0
    ) {
        $result = false;
    }
    while (strlen($topic) > 0) {
        if ($topic[0] == '+' || $topic[0] == '#') {
            return false; // MOSQ_ERR_INVAL
        }
        $topic=substr($topic, 1);
    }
    return $result;
}



//This part of code is convertion of tests for mosquitto_topic_matches_sub
// https://github.com/eclipse/mosquitto/blob/master/test/unit/util_topic_test.c

// // TEST_empty_input
// // Should return false
// echo mosquitto_topic_matches_sub("sub", NULL)?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub(NULL, "topic")?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub(NULL, NULL)?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub("sub", "")?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub("", "topic")?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub("", "")?"NOK\n":"OK\n";

// // TEST_valid_matching
// // Should return true
// echo mosquitto_topic_matches_sub("foo/#", "foo/")?"OK\n":"NOK\n";
// echo mosquitto_topic_matches_sub("foo/#", "foo")?"OK\n":"NOK\n";
// echo mosquitto_topic_matches_sub("foo//bar", "foo//bar")?"OK\n":"NOK\n";
// echo mosquitto_topic_matches_sub("foo//+", "foo//bar")?"OK\n":"NOK\n";
// echo mosquitto_topic_matches_sub("foo/+/+/baz", "foo///baz")?"OK\n":"NOK\n";
// echo mosquitto_topic_matches_sub("foo/bar/+", "foo/bar/")?"OK\n":"NOK\n";
// echo mosquitto_topic_matches_sub("foo/bar", "foo/bar")?"OK\n":"NOK\n";
// echo mosquitto_topic_matches_sub("foo/+", "foo/bar")?"OK\n":"NOK\n";
// echo mosquitto_topic_matches_sub("foo/+/baz", "foo/bar/baz")?"OK\n":"NOK\n";
// echo mosquitto_topic_matches_sub("A/B/+/#", "A/B/B/C")?"OK\n":"NOK\n";
// echo mosquitto_topic_matches_sub("foo/+/#", "foo/bar/baz")?"OK\n":"NOK\n";
// echo mosquitto_topic_matches_sub("foo/+/#", "foo/bar")?"OK\n":"NOK\n";
// echo mosquitto_topic_matches_sub("#", "foo/bar/baz")?"OK\n":"NOK\n";
// echo mosquitto_topic_matches_sub("#", "foo/bar/baz")?"OK\n":"NOK\n";
// echo mosquitto_topic_matches_sub("#", "/foo/bar")?"OK\n":"NOK\n";
// echo mosquitto_topic_matches_sub("/#", "/foo/bar")?"OK\n":"NOK\n";

// //TEST_invalid_but_matching
// // should return false
// echo mosquitto_topic_matches_sub("+foo", "+foo")?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub("fo+o", "fo+o")?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub("foo+", "foo+")?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub("+foo/bar", "+foo/bar")?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub("foo+/bar", "foo+/bar")?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub("foo/+bar", "foo/+bar")?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub("foo/bar+", "foo/bar+")?"NOK\n":"OK\n";

// echo mosquitto_topic_matches_sub("+foo", "afoo")?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub("fo+o", "foao")?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub("foo+", "fooa")?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub("+foo/bar", "afoo/bar")?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub("foo+/bar", "fooa/bar")?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub("foo/+bar", "foo/abar")?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub("foo/bar+", "foo/bara")?"NOK\n":"OK\n";

// echo mosquitto_topic_matches_sub("#foo", "#foo")?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub("fo#o", "fo#o")?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub("foo#", "foo#")?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub("#foo/bar", "#foo/bar")?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub("foo#/bar", "foo#/bar")?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub("foo/#bar", "foo/#bar")?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub("foo/bar#", "foo/bar#")?"NOK\n":"OK\n";

// echo mosquitto_topic_matches_sub("foo+", "fooa")?"NOK\n":"OK\n";

// echo mosquitto_topic_matches_sub("foo/+", "foo/+")?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub("foo/#", "foo/+")?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub("foo/+", "foo/bar/+")?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub("foo/#", "foo/bar/+")?"NOK\n":"OK\n";

// echo mosquitto_topic_matches_sub("foo/+", "foo/#")?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub("foo/#", "foo/#")?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub("foo/+", "foo/bar/#")?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub("foo/#", "foo/bar/#")?"NOK\n":"OK\n";

// //TEST_valid_no_matching
// // should return false
// echo mosquitto_topic_matches_sub("test/6/#", "test/3")?"NOK\n":"OK\n";

// echo mosquitto_topic_matches_sub("foo/bar", "foo")?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub("foo/+", "foo/bar/baz")?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub("foo/+/baz", "foo/bar/bar")?"NOK\n":"OK\n";

// echo mosquitto_topic_matches_sub("foo/+/#", "fo2/bar/baz")?"NOK\n":"OK\n";

// echo mosquitto_topic_matches_sub("/#", "foo/bar")?"NOK\n":"OK\n";

// echo mosquitto_topic_matches_sub("#", "\$SYS/bar")?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub("\$BOB/bar", "\$SYS/bar")?"NOK\n":"OK\n";

// //TEST_invalid
// // should return false
// echo mosquitto_topic_matches_sub("foo#", "foo")?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub("fo#o/", "foo")?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub("foo#", "fooa")?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub("foo+", "foo")?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub("foo/#a", "foo")?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub("#a", "foo")?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub("foo/#abc", "foo")?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub("#abc", "foo")?"NOK\n":"OK\n";
// echo mosquitto_topic_matches_sub("/#a", "foo/bar")?"NOK\n":"OK\n";
?>
