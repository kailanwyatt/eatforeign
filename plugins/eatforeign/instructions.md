# EatForeign
## Global Food Celebration Platform

# Vision

EatForeign is a global food celebration platform centered around daily cultural and food-related celebrations.

The platform combines:
- food holidays
- national celebrations
- independence days
- religious festivals
- traditional dishes
- community participation
- nearby restaurant discovery

Users can:
- discover what foods the world is celebrating today
- upload photos/videos/comments
- rate dishes
- mark celebrations as completed
- discover restaurants nearby serving featured dishes

The platform should feel like:
- a global food passport
- a living cultural calendar
- a food discovery community

NOT just a blog.

---

# Core Product Principles

## 1. Calendar-Driven
Every day has celebrations.

The calendar is the engagement engine.

## 2. Community Participation
Users participate within celebrations.

NOT random social posting.

## 3. Food Discovery
Users discover:
- cuisines
- dishes
- cultures
- nearby restaurants

## 4. SEO + Social
Website drives organic traffic.
Mobile app drives engagement and retention.

---

# Architecture

# Web Platform
- WordPress backend
- Custom theme or headless-ready architecture
- API-first internally
- SEO optimized

# Mobile App
- Expo React Native app
- Consumes WPGraphQL APIs
- Push notifications
- Geolocation
- Camera uploads

# API Layer
- WPGraphQL
- JWT Authentication
- REST fallback if needed

---

# Recommended Stack

## Backend
- WordPress
- PHP 8+
- MySQL
- WPGraphQL
- Redis object cache
- Cloudflare CDN

## Frontend Web
- WordPress custom theme
- React-enhanced components where needed
- Tailwind CSS optional

## Mobile
- Expo
- React Native
- Expo Router
- React Query / TanStack Query

## Storage
Initially:
- WordPress media library

Later:
- Cloudflare R2 or S3

## Maps / Restaurant Discovery
- Google Places API

## Search (Later)
- Typesense
or
- Algolia

---

# Core Data Models

# Custom Post Types

## celebrations
Represents:
- food holidays
- independence days
- cultural events
- religious celebrations

Fields:
- title
- slug
- event_date
- recurring_rule
- country
- celebration_type
- hero_image
- short_description
- long_description
- featured_dishes
- hashtags
- featured_restaurants
- seo_metadata

Examples:
- National Donut Day
- Jamaican Independence Day
- Lunar New Year

---

## dishes

Fields:
- title
- slug
- origin_country
- cuisine_type
- spice_level
- ingredients
- description
- image_gallery
- average_rating
- celebration_relationships

Examples:
- Pho
- Jerk Chicken
- Doubles
- Sushi

---

## restaurants

Fields:
- restaurant_name
- cuisine_types
- dishes_served
- geolocation
- address
- city
- state
- country
- website
- social_links
- images
- verified
- celebration_tags

---

## celebration_posts
User-generated posts tied to a celebration.

Fields:
- user_id
- celebration_id
- dish_id
- uploaded_images
- caption
- rating
- restaurant_id
- location
- first_time_trying
- visibility
- likes_count
- comments_count

---

# Taxonomies

## cuisine
Examples:
- Caribbean
- Korean
- Japanese

## country
Examples:
- Jamaica
- Trinidad
- Mexico

## celebration_type
Examples:
- Food Holiday
- Independence Day
- Religious Festival

## dietary_type
Examples:
- Vegan
- Seafood
- Halal

## spice_level
Examples:
- Mild
- Medium
- Hot

---

# Website Features

# Homepage

Sections:
- Today's Celebrations
- Trending Dishes
- Most Celebrated Today
- Explore by Country
- Upcoming Food Holidays
- Featured User Celebrations

---

# Celebration Pages

URL:
`/celebrations/jamaican-independence-day`

Contains:
- hero section
- cultural description
- featured dishes
- nearby restaurants
- community feed
- celebration stats
- user uploads
- comments
- ratings

---

# Dish Pages

URL:
`/dishes/jerk-chicken`

Contains:
- origin
- ingredients
- spice level
- celebrations associated
- nearby restaurants
- ratings
- photos

---

# Country Pages

URL:
`/countries/jamaica`

Contains:
- national dishes
- celebrations
- trending restaurants
- user activity
- cultural overview

---

# Mobile App Features

# Main Feed
Daily celebration feed.

Examples:
- Today is National Taco Day
- Celebrate Jamaican Independence
- World Chocolate Day

---

# Celebration Participation

Users can:
- upload photos
- add captions
- rate dishes
- tag restaurants
- mark "I Celebrated This"

---

# Food Passport

Track:
- countries explored
- dishes tried
- celebrations completed

Achievements:
- Caribbean Explorer
- Spice Adventurer
- Street Food Hunter

---

# Nearby Discovery

Users can:
- enter location
- discover nearby restaurants
- see who is selling featured dishes today

Examples:
- Jamaican food near me
- Restaurants celebrating Eid
- Korean BBQ for Chuseok

---

# Push Notifications

Examples:
- Today is National Sushi Day 🍣
- 5 restaurants near you are celebrating Jamaican Independence 🇯🇲
- Try a new dish today

---

# AI Content Automation

# Daily Automation Pipeline

Every midnight:

1. Check celebrations occurring today
2. Generate:
   - descriptions
   - captions
   - hashtags
   - social prompts
3. Publish celebration pages
4. Send notifications
5. Update homepage sections

---

# AI Content Rules

AI should:
- enrich content
- summarize celebrations
- generate metadata

AI should NOT:
- invent cultural history
- fabricate recipes
- generate inaccurate traditions

Human moderation should exist.

---

# Restaurant Discovery Strategy

# Phase 1
Use Google Places API.

Search by:
- cuisine
- dish keywords
- celebration tags

Examples:
- Jamaican restaurant
- Korean BBQ
- Pho

---

# Phase 2
Restaurants claim listings.

Restaurants can:
- upload menus
- participate in celebrations
- sponsor celebrations

---

# Authentication

Support:
- Email/password
- Google login
- Apple login
- Facebook login

---

# MVP PRIORITIES

# Phase 1 (MVP)
Build:
- celebration calendar
- today and month calendar views
- celebration pages
- dishes and food directory
- country hub pages
- user posts
- ratings
- comments
- celebration completion toggles
- dish eat votes and post likes
- derived food passport reads
- basic nearby restaurant discovery
- auth bootstrap and profile/location preferences

DO NOT build:
- messaging
- complex follows
- advanced social networking

---

# Phase 2
Build:
- push notifications
- passport badges and achievements
- saved celebrations
- restaurant claiming
- Google Places enrichment
- OAuth providers beyond email/password

---

# Backend plugin scaffold

The WordPress plugin lives at `wp-content/plugins/eatforeign/` and is API-first for the web demo and Expo mobile demo.

## Editorial custom post types

- `ef_celebration`
- `ef_dish`
- `ef_country`
- `ef_restaurant`

## Community custom post types

- `ef_celebration_post`
- `ef_comment`

## Taxonomies

- `ef_cuisine`
- `ef_country`
- `ef_celebration_type`
- `ef_dish_type`
- `ef_dietary_type`
- `ef_spice_level`

## CRUD ownership

- Editorial catalog content is created, updated, and deleted in wp-admin.
- Community posts, comments, likes, ratings, eat votes, celebration completions, and profile/location preferences are created and updated through authenticated API mutations.
- Passport summaries are derived from user activity and exposed read-only.

## REST bootstrap

- `POST /wp-json/eatforeign/v1/auth/register`
- `POST /wp-json/eatforeign/v1/auth/login`
- `GET /wp-json/eatforeign/v1/bootstrap`

## GraphQL contract

Requires WPGraphQL. Root queries include `todayCelebrations`, `celebrationBySlug`, `dishBySlug`, `directoryDishes`, `passports`, and `passportBySlug`. Root mutations include `createCelebrationPost`, `toggleCelebrationCompleted`, `rateDish`, `setDishEatVote`, `togglePostLike`, and `updateProfile`.

## Demo selector mapping

- `getTodayCelebrations` -> `todayCelebrations`
- `getCelebrationsForCalendarMonth` -> month-grouped celebration queries
- `filterDirectoryDishes` -> `directoryDishes`
- `getCelebrationBySlug` -> `celebrationBySlug`
- `getDishBySlug` -> `dishBySlug`
- `getAllPassports` / `getPassportBySlug` -> `passports` / `passportBySlug`
- `DemoContext` write actions -> GraphQL mutations listed above

## Client routes to preserve

- `/`, `/today`, `/calendar`, `/directory`, `/passport`, `/passport/:slug`, `/celebrations/:slug`, `/dishes/:slug`, `/countries/:slug`, `/login`, `/register`, `/terms`, `/privacy`

---

# Phase 3
Build:
- creator profiles
- monetization
- sponsored celebrations
- premium discovery tools

---

# SEO Strategy

Primary traffic sources:
- Google Search
- Pinterest
- TikTok
- Instagram Reels
- YouTube Shorts

SEO page examples:
- /today
- /celebrations/national-pizza-day
- /countries/japan
- /dishes/pho
- /months/october-food-holidays

---

# Product Goal

EatForeign should become:

"The world's food celebration calendar."

Users should return daily to:
- discover foods
- celebrate cultures
- share experiences
- explore restaurants
- try something new