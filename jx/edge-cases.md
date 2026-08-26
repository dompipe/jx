# Edge cases (Resistant stress)

1. Delivery into missing structure  
2. Delivery into const  
3. Quotient exhaustion  
4. Sign/unsign races  
5. Complex overflow/inf  
6. Const-cast violations  
7. Hostile dynamic shapes  
8. One-shot sign-and-write over capacity  
9. Cross-Page push without ref  
10. Resistant markers must be introspectable  

Fail closed; never crash the server on Bag overflow.
