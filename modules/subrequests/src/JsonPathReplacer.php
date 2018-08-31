<?php

namespace Drupal\subrequests;

use JsonPath\JsonObject;
use Drupal\Component\Serialization\Json;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class JsonPathReplacer {

  /**
   * Performs the JSON Path replacements in the whole batch.
   *
   * @param \Drupal\subrequests\Subrequest[] $batch
   *   The subrequests that contain replacement tokens.
   * @param \Symfony\Component\HttpFoundation\Response[] $responses
   *   The accumulated responses from previous requests.
   *
   * @return \Drupal\subrequests\Subrequest[]
   *   An array of subrequests. Note that one input subrequest can generate N
   *   output subrequests. This is because JSON path expressinos can return
   *   multiple values.
   */
  public function replaceBatch(array $batch, array $responses) {
    return array_reduce($batch, function (array $carry, Subrequest $subrequest) use ($responses) {
      return array_merge(
        $carry,
        $this->replaceItem($subrequest, $responses)
      );
    }, []);
  }

  /**
   * Searches for JSONPath tokens in the request and replaces it with the values
   * from previous responses.
   *
   * @param \Drupal\subrequests\Subrequest $subrequest
   *   The list of requests that can contain tokens.
   * @param \Symfony\Component\HttpFoundation\Response[] $pool
   *   The pool of responses that can content the values to replace.
   *
   * @returns \Drupal\subrequests\Subrequest[]
   *   The new list of requests. Note that if a JSONPath token yields many
   *   values then several replaced subrequests will be generated from the input
   *   subrequest.
   */
  protected function replaceItem(Subrequest $subrequest, array $pool) {
    $token_replacements = [
      'uri' => $this->extractTokenReplacements($subrequest, 'uri', $pool),
      'body' => $this->extractTokenReplacements($subrequest, 'body', $pool),
    ];
    if (count($token_replacements['uri']) !== 0) {
      return $this->replaceBatch(
        $this->doReplaceTokensInLocation($token_replacements, $subrequest, 'uri'),
        $pool
      );
    }
    if (count($token_replacements['body']) !== 0) {
      return $this->replaceBatch(
        $this->doReplaceTokensInLocation($token_replacements, $subrequest, 'body'),
        $pool
      );
    }
    // If there are no replacements necessary, then just return the initial
    // request.
    $subrequest->_resolved = TRUE;
    return [$subrequest];
  }

  /**
   * Creates replacements for either the body or the URI.
   *
   * @param array $token_replacements
   *   Holds the info to replace text.
   * @param \Drupal\subrequests\Subrequest $tokenized_subrequest
   *   The original copy of the subrequest.
   * @param string $token_location
   *   Either 'body' or 'uri'.
   *
   * @returns \Drupal\subrequests\Subrequest[]
   *   The replaced subrequests.
   *
   * @private
   */
  protected function doReplaceTokensInLocation(array $token_replacements, $tokenized_subrequest, $token_location) {
    $replacements = [];
    $tokens_per_content_id = $token_replacements[$token_location];
    $index = 0;
    // First figure out the different token resolutions and their token.
    $grouped_by_token = [];
    foreach ($tokens_per_content_id as $resolutions_per_token) {
      foreach ($resolutions_per_token as $token => $resolutions) {
        $grouped_by_token[] = array_map(function ($resolution) use ($token) {
          return [
            'token' => $token,
            'value' => $resolution,
          ];
        }, $resolutions);
      }
    }
    // Then calculate the points.
    $points = $this->getPoints($grouped_by_token);
    foreach ($points as $point) {
      // Clone the subrequest.
      $cloned = clone $tokenized_subrequest;
      $cloned->requestId = sprintf(
        '%s#%s{%s}',
        $tokenized_subrequest->requestId,
        $token_location,
        $index
      );
      $index++;
      // Now replace all the tokens in the request member.
      $token_subject = $this->serializeMember($token_location, $cloned->{$token_location});
      foreach($point as $replacement) {
        // Do all the different replacements on the same subject.
        $token_subject = $this->replaceTokenSubject(
          $replacement['token'],
          $replacement['value'],
          $token_subject
        );
      }
      $cloned->{$token_location} = $this->deserializeMember($token_location, $token_subject);
      array_push($replacements, $cloned);
    }
    return $replacements;
  }

  /**
   * Does the replacement on the token subject.
   *
   * @param string $token
   *   The thing to replace.
   * @param string $value
   *   The thing to replace it with.
   * @param string $token_subject
   *   The thing to replace it on.
   *
   * @returns string
   *   The replaced string.
   */
  protected function replaceTokenSubject($token, $value, $token_subject) {
    // Escape regular expression.
    $regexp = sprintf('/%s/', preg_quote($token), '/');
    return preg_replace($regexp, $value, $token_subject);
  }

  /**
   * Generates a list of sets of coordinates for the token replacements.
   *
   * Each point (coordinates set) end up creating a new clone of the tokenized
   * subrequest.
   *
   * @param array $grouped_by_token
   *   Replacements grouped by token.
   *
   * @return array
   *   The coordinates sets.
   */
  protected function getPoints($grouped_by_token) {
    $current_group = array_shift($grouped_by_token);
    // If this is not the last group, then call recursively.
    if (empty($grouped_by_token)) {
      return array_map(function ($item) {
        return [$item];
      }, $current_group);
    }
    $points = [];
    foreach ($current_group as $resolution_info) {
      // Get all the combinations for the next groups.
      $next_points = $this->getPoints($grouped_by_token);
      foreach ($next_points as $next_point) {
        // Prepend the current resolution for each point.
        $points[] = array_merge([$resolution_info], $next_point);
      }
    }
    return $points;
  }

  /**
   * Makes sure that the subject for replacement is a string.
   *
   * This is an abstraction to be able to treat 'uri' and 'body' replacements
   * the same way.
   *
   * @param string $member_name
   *   Either 'body' or 'uri'.
   * @param mixed $value
   *   The contents of the URI or the subrequest body.
   *
   * @returns string
   *   The serialized member.
   */
  protected function serializeMember($member_name, $value) {
    return $member_name === 'body'
      // The body is an Object, to replace on it we serialize it first.
      ? Json::encode($value)
      : $value;
  }

  /**
   * Undoes the serialization that happened in _serializeMember.
   *
   * This is an abstraction to be able to treat 'uri' and 'body' replacements
   * the same way.
   *
   * @param string $member_name
   *   Either 'body' or 'uri'.
   * @param string $serialized
   *   The contents of the serialized URI or the serialized subrequest body.
   *
   * @returns mixed
   *   The unserialized member.
   */
  protected function deserializeMember($member_name, $serialized) {
    return $member_name === 'body'
      // The body is an Object, to replace on it we serialize it first.
      ? Json::decode($serialized)
      : $serialized;
  }

  /**
   * Extracts the token replacements for a given subrequest.
   *
   * Given a subrequest there can be N tokens to be replaced. Each token can
   * result in an list of values to be replaced. Each token may refer to many
   * subjects, if the subrequest referenced in the token ended up spawning
   * multiple responses. This function detects the tokens and finds the
   * replacements for each token. Then returns a data structure that contains a
   * list of replacements. Each item contains all the replacement needed to get
   * a response for the initial request, given a particular subject for a
   * particular JSONPath replacement.
   *
   * @param \Drupal\subrequests\Subrequest $subrequest
   *   The subrequest that contains the tokens.
   * @param string $token_location
   *   Indicates if we are dealing with body or URI replacements.
   * @param \Symfony\Component\HttpFoundation\Response[] pool
   *   The collection of prior responses available for use with JSONPath.
   *
   * @returns array
   *   The structure containing a list of replacements for a subject response
   *   and a replacement candidate.
   */
  protected function extractTokenReplacements(Subrequest $subrequest, $token_location, array $pool) {
    // Turn the subject into a string.
    $regexp_subject = $token_location === 'body'
      ? Json::encode($subrequest->body)
      : $subrequest->uri;
    // First find all the replacements to do. Use a regular expression to detect
    // cases like "…{{req1.body@$.data.attributes.seasons..id}}…"
    $found = $this->findTokens($regexp_subject);
    // Make sure that duplicated tokens in the same location are treated as the
    // same thing.
    $found = array_values(array_reduce($found, function ($carry, $match) {
      $carry[$match[0]] = $match;
      return $carry;
    }, []));
    // Then calculate the replacements we will need to return.
    $reducer = function ($token_replacements, $match) use ($pool) {
      // Remove the .body part at the end since we only support the body
      // replacement at this moment.
      $provided_id = preg_replace('/\.body$/', '', $match[1]);
      // Calculate what are the subjects to execute the JSONPath against.
      $subjects = array_filter($pool, function (Response $response) use ($provided_id) {
        // The response is considered a subject if it matches the content ID or
        // it is a generated copy based of that content ID.
        $pattern = sprintf('/%s(#.*)?/', preg_quote($provided_id));
        $content_id = $this->getContentId($response);
        return preg_match($pattern, $content_id);
      });
      if (count($subjects) === 0) {
        $candidates = array_map(function ($response) {
          $candidate = $this->getContentId($response);
          return preg_replace('/#.*/', '', $candidate);
        }, $pool);
        throw new BadRequestHttpException(sprintf(
          'Unable to find specified request for a replacement %s. Candidates are [%s].',
          $provided_id,
          implode(', ', $candidates)
        ));
      }
      // Find the replacements for this match given a subject. If there is more
      // than one response object (a subject) for a given subrequest, then we
      // generate one parallel subrequest per subject.
      foreach ($subjects as $subject) {
        $this->addReplacementsForSubject($match, $subject, $provided_id, $token_replacements);
      }

      return $token_replacements;
    };
    return array_reduce($found, $reducer, []);
  }

  /**
   * Gets the clean Content ID for a response.
   *
   * Removes all the derived indicators and the surrounding angles.
   *
   * @param \Symfony\Component\HttpFoundation\Response $response
   *   The response to extract the Content ID from.
   *
   * @returns string
   *   The content ID.
   */
  protected function getContentId(Response $response) {
    $header = $response->headers->get('Content-ID', '');
    return substr($header, 1, strlen($header) - 2);
  }

  /**
   * Finds and parses all the tokens in a given string.
   *
   * @param string $subject
   *   The tokenized string. This is usually the URI or the serialized body.
   *
   * @returns array
   *   A list of all the matches. Each match contains the token, the subject to
   *   search replacements in and the JSONPath query to execute.
   */
  protected function findTokens($subject) {
    $matches = [];
    $pattern = '/\{\{([^\{\}]+\.[^\{\}]+)@([^\{\}]+)\}\}/';
    preg_match_all($pattern, $subject, $matches);
    if (!$matches = array_filter($matches)) {
      return [];
    }
    $output = [];
    for ($index = 0; $index < count($matches[0]); $index++) {
      // We only care about the first three items: full match, subject ID and
      // JSONPath query.
      $output[] = [
        $matches[0][$index],
        $matches[1][$index],
        $matches[2][$index]
      ];
    }
    return $output;
  }

  /**
   * Fill replacement values for a subrequest a subject and an structured token.
   *
   * @param array $match
   *   The structured replacement token.
   * @param \Symfony\Component\HttpFoundation\Response $subject
   *   The response object the token refers to.
   * @param array $token_replacements
   *   The accumulated replacements. Adds items onto the array.
   */
  protected function addReplacementsForSubject(array $match, Response $subject, $provided_id, array &$token_replacements) {
    $json_object = new JsonObject($subject->getContent());
    $to_replace = $json_object->get($match[2]) ?: [];
    $token = $match[0];
    // The replacements need to be strings. If not, then the replacement
    // is not valid.
    $this->validateJsonPathReplacements($to_replace);
    $token_replacements[$provided_id] = empty($token_replacements[$provided_id])
      ? []
      : $token_replacements[$provided_id];
    $token_replacements[$provided_id][$token] = empty($token_replacements[$provided_id][$token])
      ? []
      : $token_replacements[$provided_id][$token];
    $token_replacements[$provided_id][$token] = array_merge($token_replacements[$provided_id][$token], $to_replace);
  }

  /**
   * Validates tha the JSONPath query yields a string or an array of strings.
   *
   * @param array $to_replace
   *   The replacement candidates.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   When the replacements are not valid.
   */
  protected function validateJsonPathReplacements($to_replace) {
    $is_valid = is_array($to_replace)
      && array_reduce($to_replace, function ($valid, $replacement) {
        return $valid && (is_string($replacement) || is_int($replacement));
      }, TRUE);
    if (!$is_valid) {
      throw new BadRequestHttpException(sprintf(
        'The replacement token did find not a list of strings. Instead it found %s.',
        Json::encode($to_replace)
      ));
    }
  }

}
