import http from 'k6/http';
import exec from 'k6/execution';
import { check, fail } from 'k6';
import { Counter, Rate } from 'k6/metrics';

const vus = Number(__ENV.K6_VUS || 100);
const iterations = Number(__ENV.K6_ITERATIONS || 200);

export const options = {
  setupTimeout: __ENV.K6_SETUP_TIMEOUT || '5m',
  scenarios: {
    checkout_contention: {
      executor: 'shared-iterations',
      vus,
      iterations,
      maxDuration: __ENV.K6_MAX_DURATION || '2m',
    },
  },
  thresholds: {
    http_req_failed: ['rate==0'],
    unexpected_checkout_status: ['rate==0'],
    checks: ['rate>0.99'],
  },
};

const successfulReservations = new Counter('successful_reservations');
const soldOutResponses = new Counter('sold_out_responses');
const unexpectedCheckoutStatus = new Rate('unexpected_checkout_status');

function json(response) {
  try {
    return response.json();
  } catch (_) {
    return null;
  }
}

function hasString(value) {
  return typeof value === 'string';
}

function hasInteger(value) {
  return Number.isInteger(value);
}

export function setup() {
  const baseUrl = __ENV.K6_BASE_URL || 'http://kong:8000';
  const eventId = Number(__ENV.K6_EVENT_ID || 0);
  const userCount = Number(__ENV.K6_USER_COUNT || iterations);
  const seededUsersJson = __ENV.K6_USERS_JSON || '';

  if (!eventId) {
    fail('K6_EVENT_ID is required.');
  }

  if (!seededUsersJson) {
    fail('K6_USERS_JSON is required.');
  }

  let seededUsers;

  try {
    seededUsers = JSON.parse(seededUsersJson);
  } catch (_) {
    fail('K6_USERS_JSON is not valid JSON.');
  }

  const users = Array.isArray(seededUsers && seededUsers.users) ? seededUsers.users : [];

  const validUsers = check(users, {
    'setup received enough pre-seeded users': (receivedUsers) => receivedUsers.length >= userCount,
    'setup received valid access tokens': (receivedUsers) => receivedUsers.slice(0, userCount).every((user) => {
      return hasInteger(user && user.id) && hasString(user && user.access_token);
    }),
  });

  if (!validUsers) {
    fail('K6_USERS_JSON does not contain enough valid users for the requested iteration count.');
  }

  const preparedUsers = users.slice(0, userCount).map((user) => ({
    id: Number(user.id),
    accessToken: String(user.access_token),
  }));

  return {
    baseUrl,
    eventId,
    users: preparedUsers,
  };
}

export default function (data) {
  const iteration = exec.scenario.iterationInTest;
  const user = data.users[iteration];

  if (!user) {
    fail(`No setup user found for iteration ${iteration}.`);
  }

  const response = http.post(
    `${data.baseUrl}/inventory/events/${data.eventId}/checkout`,
    '{}',
    {
      headers: {
        Authorization: `Bearer ${user.accessToken}`,
        'Content-Type': 'application/json',
      },
      responseCallback: http.expectedStatuses(201, 409),
    },
  );

  const body = json(response);
  const responseEventId = body && body.event_id;
  const responseUserId = body && body.user_id;
  const responseMessage = body && body.message;
  const created = response.status === 201
    && Number(responseEventId) === data.eventId
    && Number(responseUserId) === user.id;
  const soldOut = response.status === 409
    && responseMessage === 'Tickets are sold out for this event.';
  const expectedOutcome = created || soldOut;

  unexpectedCheckoutStatus.add(!expectedOutcome);

  check(response, {
    'checkout returns expected outcome': () => expectedOutcome,
  });

  if (created) {
    successfulReservations.add(1);
  }

  if (soldOut) {
    soldOutResponses.add(1);
  }
}
