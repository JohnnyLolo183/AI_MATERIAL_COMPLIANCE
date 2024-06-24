import OpenAI from "openai";

const openai = new OpenAI({
    organization: "org-org-u3J0qaQeaUSb0Q0UfnyrX9SQ",
    project: "$proj_S8pyLO3R8aaf67WrFkgNkgk9",
});

// curl https://api.openai.com/v1/chat/completions \
//  -H "Content-Type: application/json" \
// -H "Authorization: Bearer sk-proj-QjYL9AmyqUe1dM0Ge6OLT3BlbkFJJNtGw45LT8v2G8nNMYys" \
//  -d '{
//    "model": "gpt-3.5-turbo",
//    "messages": [{"role": "user", "content": "Say this is a test!"}],
//    "temperature": 0.7
//   }' ``