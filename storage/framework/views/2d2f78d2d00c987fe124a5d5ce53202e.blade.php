---
name: inertia-vue-development
description: "Develops Inertia.js v2 Vue client-side applications. Activates when creating Vue pages, forms, or navigation; using <Link>, <Form>, useForm, or router; working with deferred props, prefetching, or polling; or when user mentions Vue with Inertia, Vue pages, Vue forms, or Vue navigation."
license: MIT
metadata:
  author: laravel
---
@php
/** @var \Laravel\Boost\Install\GuidelineAssist $assist */
@endphp
# Inertia Vue development

## Documentation

Use ___SINGLE_BACKTICK___search-docs___SINGLE_BACKTICK___ for detailed Inertia v2 Vue patterns and documentation.

## Basic usage

### Page components location

Place Vue page components in the ___SINGLE_BACKTICK___{{ $assist->inertia()->pagesDirectory() }}___SINGLE_BACKTICK___ directory.

### Page component structure

Important: Vue components must have a single root element.

@verbatim
___BOOST_SNIPPET_0___
@endverbatim

## Client-side navigation

### Basic Link component

Use ___SINGLE_BACKTICK___<Link>___SINGLE_BACKTICK___ for client-side navigation instead of ___SINGLE_BACKTICK___<a>___SINGLE_BACKTICK___ tags:

___BOOST_SNIPPET_1___

### Link with method

___BOOST_SNIPPET_2___

### Prefetching

Prefetch pages to improve perceived performance:

___BOOST_SNIPPET_3___

### Programmatic navigation

___BOOST_SNIPPET_4___

## Form handling

@if($assist->inertia()->hasFormComponent())
### Form component (recommended)

The recommended way to build forms is with the ___SINGLE_BACKTICK___<Form>___SINGLE_BACKTICK___ component:

@verbatim
___BOOST_SNIPPET_5___
@endverbatim

### Form component with all props

@verbatim
___BOOST_SNIPPET_6___
@endverbatim

@if($assist->inertia()->hasFormComponentResets())
### Form component reset props

The ___SINGLE_BACKTICK___<Form>___SINGLE_BACKTICK___ component supports automatic resetting:

- ___SINGLE_BACKTICK___resetOnError___SINGLE_BACKTICK___: reset form data when the request fails
- ___SINGLE_BACKTICK___resetOnSuccess___SINGLE_BACKTICK___: reset form data when the request succeeds
- ___SINGLE_BACKTICK___setDefaultsOnSuccess___SINGLE_BACKTICK___: update default values on success

For details, run the ___SINGLE_BACKTICK___search-docs___SINGLE_BACKTICK___ tool with the query ___SINGLE_BACKTICK___form component resetting___SINGLE_BACKTICK___.

@verbatim
___BOOST_SNIPPET_7___
@endverbatim
@else
Note: This version of Inertia does not support ___SINGLE_BACKTICK___resetOnError___SINGLE_BACKTICK___, ___SINGLE_BACKTICK___resetOnSuccess___SINGLE_BACKTICK___, or ___SINGLE_BACKTICK___setDefaultsOnSuccess___SINGLE_BACKTICK___ on the ___SINGLE_BACKTICK___<Form>___SINGLE_BACKTICK___ component. Using these props causes errors. To use these props, upgrade to Inertia v2.2.0+.
@endif

For more programmatic control, you can also build forms with the ___SINGLE_BACKTICK___useForm___SINGLE_BACKTICK___ composable. For guidance, run the ___SINGLE_BACKTICK___search-docs___SINGLE_BACKTICK___ tool with the query ___SINGLE_BACKTICK___useForm helper___SINGLE_BACKTICK___.

@endif

### ___SINGLE_BACKTICK___useForm___SINGLE_BACKTICK___ composable

@if($assist->inertia()->hasFormComponent() === false)
In Inertia v2.0.x, build forms with the ___SINGLE_BACKTICK___useForm___SINGLE_BACKTICK___ composable. The ___SINGLE_BACKTICK___<Form>___SINGLE_BACKTICK___ component is not available until v2.1.0+.
@else
For more programmatic control or to follow existing conventions, use the ___SINGLE_BACKTICK___useForm___SINGLE_BACKTICK___ composable:
@endif

@verbatim
___BOOST_SNIPPET_8___
@endverbatim

## Inertia v2 features

### Deferred props

Use deferred props to load data after the initial page render:

@verbatim
___BOOST_SNIPPET_9___
@endverbatim

### Polling

Use the ___SINGLE_BACKTICK___usePoll___SINGLE_BACKTICK___ composable to refresh data automatically at intervals. The composable cleans up on unmount and throttles polling when the browser tab is inactive.

___BOOST_SNIPPET_10___

___BOOST_SNIPPET_11___

- ___SINGLE_BACKTICK___autoStart___SINGLE_BACKTICK___ (default ___SINGLE_BACKTICK___true___SINGLE_BACKTICK___): set to ___SINGLE_BACKTICK___false___SINGLE_BACKTICK___ to start polling manually with the returned ___SINGLE_BACKTICK___start()___SINGLE_BACKTICK___ function
- ___SINGLE_BACKTICK___keepAlive___SINGLE_BACKTICK___ (default ___SINGLE_BACKTICK___false___SINGLE_BACKTICK___): set to ___SINGLE_BACKTICK___true___SINGLE_BACKTICK___ to prevent throttling when the browser tab is inactive

### WhenVisible (infinite scroll)

Load more data when the user scrolls to a specific element:

@verbatim
___BOOST_SNIPPET_12___
@endverbatim

## Server-side patterns

The inertia-laravel guidelines cover server-side patterns such as Inertia::render, props, and middleware.

## Common pitfalls

- Using traditional ___SINGLE_BACKTICK___<a>___SINGLE_BACKTICK___ links instead of Inertia's ___SINGLE_BACKTICK___<Link>___SINGLE_BACKTICK___ component (breaks SPA behavior)
- Forgetting that Vue components must have a single root element
- Forgetting to add loading states (skeleton screens) when using deferred props
- Not handling the ___SINGLE_BACKTICK___undefined___SINGLE_BACKTICK___ state of deferred props before data loads
- Using ___SINGLE_BACKTICK___<form>___SINGLE_BACKTICK___ without preventing default submission (use the ___SINGLE_BACKTICK___<Form>___SINGLE_BACKTICK___ component or ___SINGLE_BACKTICK___@submit.prevent___SINGLE_BACKTICK___)
- Forgetting to check whether the ___SINGLE_BACKTICK___<Form>___SINGLE_BACKTICK___ component is available in your Inertia version
