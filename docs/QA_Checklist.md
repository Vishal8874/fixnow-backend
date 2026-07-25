# FixNow QA Checklist

## Authentication

- Register customer with valid data
- Register provider with valid data
- Validate duplicate email rejection
- Validate password confirmation
- Login with valid email and password
- Reject invalid credentials
- Reject suspended user login
- Verify `me` returns authenticated user
- Verify logout invalidates current token
- Verify login throttling
- Verify register throttling
- Verify Google callback throttling

## Customer

- Create first address and verify it becomes default
- Create second address and verify only one default exists
- Update address fields
- Set another address as default
- Delete default address and verify replacement default selection
- Verify customer cannot access another customer address

## Provider

- Provider can create profile once
- Provider can update own profile
- Provider cannot create duplicate profile
- Provider can create availability once
- Provider can update availability
- Provider can only access own assignments
- Provider can accept assignment
- Provider can reject assignment
- Provider can complete accepted assignment

## Admin

- CRUD categories
- CRUD services
- List pending providers
- Approve provider
- Reject provider
- View reviews
- Mark online payment success
- Mark online payment failed
- Mark COD payment paid only after completion
- Use operational manual assignment endpoint

## Booking

- Create booking with active category and service
- Reject inactive service booking
- Verify booking totals
- Verify booking number is generated
- Verify booking history includes `created`
- View booking list
- View booking detail
- Cancel booking
- Verify ownership enforcement

## Payment

- Create COD payment
- Create online payment
- Verify only one payment per booking
- Verify online success triggers pending assignment
- Verify COD creation triggers pending assignment
- Verify payment detail ownership

## Assignment

- Verify automatic assignment after eligible payment flow
- Verify provider eligibility checks
- Verify service area matching
- Verify service matching
- Verify active provider status requirement
- Verify approved provider profile requirement
- Verify availability record requirement
- Verify availability time window filtering
- Verify no duplicate active assignments
- Verify automatic reassignment after rejection
- Verify graceful no-provider-available behavior

## Reviews

- Create review only for completed paid booking
- Reject duplicate review
- Reject review for unpaid booking
- Reject review without valid assignment history
- Edit review within 24 hours
- Reject edit after 24 hours
- Verify provider rating recalculation
- Verify admin read-only access

## Security

- Verify admin routes reject customer/provider users
- Verify customer routes reject admin/provider users
- Verify provider routes reject admin/customer users
- Verify no raw model exposure
- Verify hidden password fields are never returned

## Validation

- Invalid category payload
- Invalid service payload
- Invalid address payload
- Invalid booking payload
- Invalid payment payload
- Invalid provider profile payload
- Invalid provider availability payload
- Invalid review payload
- Verify standard validation response shape

## Authorization

- Customer cannot view another customer booking
- Customer cannot view another customer payment
- Customer cannot view another customer review
- Provider cannot access another provider assignment
- Admin-only provider approval actions stay protected
